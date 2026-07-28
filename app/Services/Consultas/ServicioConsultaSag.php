<?php

namespace App\Services\Consultas;

use App\Exceptions\ServicioSagNoDisponible;
use App\Models\ConsultaSag;
use App\Models\CsgValidacion;
use App\Models\ProductorCsg;
use App\Models\User;
use App\Services\Validacion\ServicioSincronizacionCatalogoSag;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ServicioConsultaSag
{
    public const URL = 'https://sra.sag.gob.cl/SRA_COMUNES/SRA_ContComunExt.asp?opcMenu=BusCodSAG';

    public function __construct(
        private readonly ServicioSincronizacionCatalogoSag $sincronizadorCatalogo,
    ) {}

    /** @return Collection<int, ProductorCsg> */
    public function consultar(string $tipo, string $valor, User $usuario): Collection
    {
        $inicio = hrtime(true);

        try {
            $cookies = new CookieJar;
            $cliente = Http::withOptions(['cookies' => $cookies])
                ->accept('text/html,application/xhtml+xml')
                ->withHeaders([
                    'User-Agent' => 'Estiba-WMS/1.0 (consulta-operacional-csg)',
                ])
                ->connectTimeout(5)
                ->timeout(15);
            $entrada = $cliente->get(self::URL);
            if (! $entrada->successful()) {
                throw new ServicioSagNoDisponible(
                    "SAG respondió con estado HTTP {$entrada->status()}. Intenta nuevamente.",
                );
            }

            $respuesta = $cliente->asForm()->post(self::URL, [
                'opcMenu' => 'AsocProv',
                'codigos' => '',
                'idpred' => '',
                'searchRutCodigo' => $tipo === 'rut' ? 'Rut' : 'Codigo SAG',
                'rut_part' => $tipo === 'rut' ? $valor : '',
                'tipo_part' => '2',
                'cod_sag' => $tipo === 'codigo_sag' ? $valor : '',
                'Buscar' => 'Buscar',
            ]);

            if (! $respuesta->successful()) {
                throw new ServicioSagNoDisponible(
                    "SAG respondió con estado HTTP {$respuesta->status()}. Intenta nuevamente.",
                );
            }

            $resultados = $this->extraerResultados($respuesta->body());
            $productores = DB::transaction(function () use (
                $resultados,
                $tipo,
                $valor,
                $usuario,
            ): Collection {
                $ahora = now();

                return $resultados->map(function (array $resultado) use (
                    $tipo,
                    $valor,
                    $usuario,
                    $ahora,
                ): ProductorCsg {
                    $productor = ProductorCsg::query()->firstOrNew([
                        'codigo' => $resultado['codigo'],
                    ]);
                    $primeraVerificacion = $productor->exists
                        ? $productor->primera_verificacion_at
                        : $ahora;

                    $productor->fill([
                        ...$resultado,
                        'rut' => $tipo === 'rut' ? $valor : $productor->rut,
                        'fuente_url' => self::URL,
                        'primera_verificacion_at' => $primeraVerificacion,
                        'ultima_verificacion_at' => $ahora,
                        'ultima_consulta_user_id' => $usuario->id,
                        'respuesta_hash' => hash('sha256', json_encode($resultado, JSON_UNESCAPED_UNICODE)),
                        'datos_fuente' => $resultado,
                    ])->save();

                    CsgValidacion::query()
                        ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper($resultado['codigo'])])
                        ->whereNull('productor_csg_id')
                        ->update(['productor_csg_id' => $productor->id]);

                    $this->sincronizadorCatalogo->sincronizar(
                        $productor,
                        $resultado['especies_variedades'],
                    );

                    return $productor;
                });
            });

            $this->auditar(
                $tipo,
                $valor,
                $productores->isEmpty() ? 'sin_resultados' : 'exitosa',
                $productores->count(),
                $inicio,
                $usuario,
            );

            return $productores->each->load(['clientes', 'catalogosTemporada.temporada']);
        } catch (ServicioSagNoDisponible $exception) {
            $this->auditar($tipo, $valor, 'error', 0, $inicio, $usuario, $exception->getMessage());
            throw $exception;
        } catch (ConnectionException $exception) {
            $mensaje = 'No fue posible conectar con SAG. Intenta nuevamente en unos minutos.';
            $this->auditar($tipo, $valor, 'error', 0, $inicio, $usuario, $mensaje);
            throw new ServicioSagNoDisponible($mensaje, previous: $exception);
        } catch (Throwable $exception) {
            report($exception);
            $mensaje = 'La respuesta de SAG no pudo procesarse. No se modificaron productores.';
            $this->auditar($tipo, $valor, 'error', 0, $inicio, $usuario, $mensaje);
            throw new ServicioSagNoDisponible($mensaje, previous: $exception);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function extraerResultados(string $html): Collection
    {
        $utf8 = $this->convertirAUtf8($html);
        $documento = new DOMDocument;
        $estadoLibxml = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8">'.$utf8);
        libxml_clear_errors();
        libxml_use_internal_errors($estadoLibxml);
        $xpath = new DOMXPath($documento);
        $resultados = collect();

        foreach ($xpath->query('//tr[td]') ?: [] as $fila) {
            $celdas = $xpath->query('./td', $fila);
            if (! $celdas || $celdas->length < 3) {
                continue;
            }

            $identificacion = $this->texto($celdas->item(0));
            if (! preg_match(
                '/^([A-Z0-9.-]+)\s*\(([^)]+)\)/iu',
                $identificacion,
                $coincidencia,
            ) || ! preg_match(
                '/\(\s*(CSG)\s*\)/iu',
                $identificacion,
                $coincidenciaTipo,
            )) {
                continue;
            }

            $tipoCodigo = mb_strtoupper(trim($coincidenciaTipo[1]));
            if ($tipoCodigo !== 'CSG') {
                continue;
            }

            $origen = $this->lineas($celdas->item(1));
            $especies = $this->lineas($celdas->item(3))
                ->map(fn (string $linea): string => ltrim($linea, "- \t\n\r\0\x0B"))
                ->filter()
                ->values();
            $especiesVariedades = $especies
                ->map(fn (string $linea): ?array => $this->separarEspecieVariedad($linea))
                ->filter()
                ->values();
            $resultado = [
                'codigo' => mb_strtoupper(trim($coincidencia[1])),
                'razon_social' => $this->texto($celdas->item(2)),
                'predio' => $origen->first() ?? 'Sin predio informado',
                'direccion' => $origen->skip(1)->implode(', ') ?: null,
                'estado_sag' => mb_strtolower(trim($coincidencia[2])),
                'tipo_codigo' => $tipoCodigo,
                'especies' => $especies->all(),
                'especies_variedades' => $especiesVariedades->all(),
            ];

            if ($resultado['codigo'] !== '' && $resultado['razon_social'] !== '') {
                $resultados->push($resultado);
            }
        }

        $encabezadoReconocido = collect($xpath->query('//th') ?: [])
            ->contains(fn ($celda): bool => str_contains(
                mb_strtolower($this->texto($celda)),
                'código sag',
            ));
        if (! $encabezadoReconocido) {
            throw new ServicioSagNoDisponible(
                'SAG entregó una respuesta con un formato no reconocido. No se modificaron productores.',
            );
        }

        return $resultados->unique('codigo')->values();
    }

    /** @return Collection<int, string> */
    private function lineas(?DOMElement $celda): Collection
    {
        if (! $celda) {
            return collect();
        }

        $items = $celda->getElementsByTagName('li');
        if ($items->length > 0) {
            return collect($items)
                ->map(fn ($item): string => $this->normalizarTexto($item->textContent))
                ->filter()
                ->values();
        }

        $texto = '';
        foreach ($celda->childNodes as $nodo) {
            $texto .= $nodo->nodeName === 'br' ? "\n" : $nodo->textContent;
        }

        return collect(preg_split('/[\r\n•]+/u', $texto) ?: [])
            ->map(fn (string $linea): string => $this->normalizarTexto($linea))
            ->filter()
            ->values();
    }

    /**
     * @return array{especie: string, variedad: string, texto: string}|null
     */
    private function separarEspecieVariedad(string $linea): ?array
    {
        $partes = preg_split('/\s+-\s+/u', $linea, 2);
        if (! $partes || count($partes) !== 2) {
            return null;
        }

        $especie = $this->normalizarTexto($partes[0]);
        $variedad = $this->normalizarTexto($partes[1]);
        if ($especie === '' || $variedad === '') {
            return null;
        }

        return [
            'especie' => $especie,
            'variedad' => $variedad,
            'texto' => "{$especie} - {$variedad}",
        ];
    }

    private function convertirAUtf8(string $html): string
    {
        $contenido = str_starts_with($html, "\xEF\xBB\xBF")
            ? substr($html, 3)
            : $html;

        return mb_check_encoding($contenido, 'UTF-8')
            ? $contenido
            : mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    private function texto(?DOMElement $celda): string
    {
        return $this->normalizarTexto($celda?->textContent ?? '');
    }

    private function normalizarTexto(string $texto): string
    {
        $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $reparado = $this->repararMojibake($decodificado);

        return trim(preg_replace('/\s+/u', ' ', $reparado) ?? $reparado);
    }

    private function repararMojibake(string $texto): string
    {
        for ($intento = 0; $intento < 2; $intento++) {
            $puntajeActual = $this->puntajeMojibake($texto);
            if ($puntajeActual === 0) {
                break;
            }

            $candidato = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
            if (
                ! mb_check_encoding($candidato, 'UTF-8')
                || $this->puntajeMojibake($candidato) >= $puntajeActual
            ) {
                break;
            }

            $texto = $candidato;
        }

        return $texto;
    }

    private function puntajeMojibake(string $texto): int
    {
        return preg_match_all('/(?:Ã.|Â.|â..|ðŸ..|�)/u', $texto) ?: 0;
    }

    private function auditar(
        string $tipo,
        string $valor,
        string $estado,
        int $cantidad,
        int $inicio,
        User $usuario,
        ?string $error = null,
    ): void {
        ConsultaSag::query()->create([
            'tipo_busqueda' => $tipo,
            'valor_normalizado' => $valor,
            'estado' => $estado,
            'cantidad_resultados' => $cantidad,
            'duracion_ms' => max(0, (int) round((hrtime(true) - $inicio) / 1_000_000)),
            'error' => $error ? mb_substr($error, 0, 500) : null,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
        ]);
    }
}
