from pathlib import Path

path = Path('app/Services/Estiba/ServicioMovimientoEstiba.php')
text = path.read_text(encoding='utf-8')

old_signature = '''    public function ubicar(
        string $operacionId,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        Camara $camaraDestino,
        ?Posicion $posicionDestino,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        array $datosFolio = [],
        array $datosMaterial = [],
        array $advertenciasConfirmadas = [],
    ): Movimiento {
        $numeroFolio = trim($numeroFolio);
'''

new_signature = '''    public function ubicar(
        string $operacionId,
        string $numeroFolio,
        TipoBulto $tipoBulto,
        ?Posicion $posicionDestino,
        SesionEstiba $sesionDestino,
        User $usuario,
        Dispositivo $dispositivo,
        int $versionDestinoConocida,
        DateTimeInterface $generadoDispositivoAt,
        array $datosFolio = [],
        array $datosMaterial = [],
        array $advertenciasConfirmadas = [],
        ?Camara $camaraDestino = null,
    ): Movimiento {
        $camaraDestino ??= $posicionDestino?->camara;

        if (! $camaraDestino) {
            throw new DomainException('La cámara de destino es obligatoria.');
        }

        $numeroFolio = trim($numeroFolio);
'''

old_message = 'El folio de material debe nacer desde Recepción o Transformación antes de asignarlo.'
new_message = 'El folio de material no existe. Debe nacer desde Recepción, Transformación o una migración controlada antes de ubicarlo.'

if text.count(old_signature) != 1:
    raise RuntimeError(f'Firma esperada encontrada {text.count(old_signature)} veces')

if text.count(old_message) != 1:
    raise RuntimeError(f'Mensaje esperado encontrado {text.count(old_message)} veces')

text = text.replace(old_signature, new_signature, 1)
text = text.replace(old_message, new_message, 1)
path.write_text(text, encoding='utf-8')
