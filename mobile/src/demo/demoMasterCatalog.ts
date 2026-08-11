export const DEMO_MASTER_CATALOG_VERSION = '1';

export const DEMO_MASTER_CATEGORIES = [
  'temporadas',
  'proveedores',
  'especies',
  'variedades',
  'calibres',
  'envases',
  'materiales',
  'destinos',
  'tuneles',
  'impresion',
] as const;
export type DemoMasterCategory = typeof DEMO_MASTER_CATEGORIES[number];

export const DEMO_MASTER_CATEGORY_LABELS: Record<DemoMasterCategory, string> = {
  temporadas: 'Temporadas',
  proveedores: 'Proveedores',
  especies: 'Especies',
  variedades: 'Variedades',
  calibres: 'Calibres',
  envases: 'Envases',
  materiales: 'Materiales',
  destinos: 'Destinos y centros de costo',
  tuneles: 'Túneles de prefrío',
  impresion: 'Perfiles de impresión',
};

export type DemoMasterSeedRecord = {
  id: string;
  category: DemoMasterCategory;
  code: string;
  name: string;
  detail: string;
};

/**
 * Catálogo comercial incluido en la APK. Es deliberadamente ficticio: conserva
 * la estructura de los maestros productivos sin revelar clientes, proveedores,
 * inventario ni configuraciones activas de una planta real.
 */
export const DEMO_MASTER_SEED: readonly DemoMasterSeedRecord[] = [
  {
    id: 'master-season-01',
    category: 'temporadas',
    code: 'TEMP-DEMO-01',
    name: 'Temporada frutícola Demo',
    detail: '01/10/2026 al 31/03/2027',
  },
  {
    id: 'master-provider-01',
    category: 'proveedores',
    code: 'PROV-DEMO-01',
    name: 'Proveedor Embalajes Demo',
    detail: 'Materiales de embalaje',
  },
  {
    id: 'master-provider-02',
    category: 'proveedores',
    code: 'PROV-DEMO-02',
    name: 'Proveedor Agrícola Demo',
    detail: 'Materia prima y envases',
  },
  { id: 'master-species-01', category: 'especies', code: 'CER', name: 'Cereza', detail: 'Fruta fresca' },
  { id: 'master-species-02', category: 'especies', code: 'ARA', name: 'Arándano', detail: 'Fruta fresca' },
  { id: 'master-species-03', category: 'especies', code: 'KIW', name: 'Kiwi', detail: 'Fruta fresca' },
  { id: 'master-variety-01', category: 'variedades', code: 'CER-SANTINA', name: 'Santina', detail: 'Cereza' },
  { id: 'master-variety-02', category: 'variedades', code: 'CER-LAPINS', name: 'Lapins', detail: 'Cereza' },
  { id: 'master-variety-03', category: 'variedades', code: 'CER-REGINA', name: 'Regina', detail: 'Cereza' },
  { id: 'master-variety-04', category: 'variedades', code: 'ARA-LEGACY', name: 'Legacy', detail: 'Arándano' },
  { id: 'master-variety-05', category: 'variedades', code: 'KIW-HAYWARD', name: 'Hayward', detail: 'Kiwi' },
  { id: 'master-caliber-01', category: 'calibres', code: 'J', name: 'J', detail: 'Calibre comercial' },
  { id: 'master-caliber-02', category: 'calibres', code: '2J', name: '2J', detail: 'Calibre comercial' },
  { id: 'master-caliber-03', category: 'calibres', code: '3J', name: '3J', detail: 'Calibre comercial' },
  { id: 'master-caliber-04', category: 'calibres', code: '4J', name: '4J', detail: 'Calibre comercial' },
  { id: 'master-package-01', category: 'envases', code: 'CAJA-5KG', name: 'Caja 5 kg', detail: 'Caja terminada' },
  { id: 'master-package-02', category: 'envases', code: 'CAJA-2_5KG', name: 'Caja 2,5 kg', detail: 'Caja terminada' },
  { id: 'master-package-03', category: 'envases', code: 'BIN-400KG', name: 'Bin 400 kg', detail: 'Envase retornable' },
  { id: 'master-material-01', category: 'materiales', code: 'MAT-CAJA-01', name: 'Caja de cartón', detail: 'unidad · embalaje' },
  { id: 'master-material-02', category: 'materiales', code: 'MAT-BOLSA-01', name: 'Bolsa de atmósfera', detail: 'unidad · embalaje' },
  { id: 'master-material-03', category: 'materiales', code: 'MAT-PALLET-01', name: 'Pallet exportación', detail: 'unidad · pallet' },
  { id: 'master-material-04', category: 'materiales', code: 'MAT-ESQUINERO-01', name: 'Esquinero', detail: 'unidad · embalaje' },
  { id: 'master-destination-01', category: 'destinos', code: 'CC-PACKING', name: 'Packing', detail: 'Centro de costo Demo' },
  { id: 'master-destination-02', category: 'destinos', code: 'CC-PROCESO', name: 'Proceso', detail: 'Centro de costo Demo' },
  { id: 'master-destination-03', category: 'destinos', code: 'CC-MANTENCION', name: 'Mantención', detail: 'Centro de costo Demo' },
  { id: 'master-tunnel-01', category: 'tuneles', code: 'TUN-01', name: 'Túnel Demo 1', detail: '20 posiciones · -1,5 °C' },
  { id: 'master-tunnel-02', category: 'tuneles', code: 'TUN-02', name: 'Túnel Demo 2', detail: '20 posiciones · -1,5 °C' },
  { id: 'master-tunnel-03', category: 'tuneles', code: 'TUN-03', name: 'Túnel Demo 3', detail: '20 posiciones · -1,5 °C' },
  { id: 'master-print-01', category: 'impresion', code: 'BIX-100X200-203', name: 'Bixolon 100 × 200 mm', detail: '203 dpi · vertical · BPL-Z' },
  { id: 'master-print-02', category: 'impresion', code: 'BIX-100X50-203', name: 'Bixolon 100 × 50 mm', detail: '203 dpi · horizontal · BPL-Z' },
  { id: 'master-print-03', category: 'impresion', code: 'ZEB-100X50-203', name: 'Zebra 100 × 50 mm', detail: '203 dpi · horizontal · ZPL' },
] as const;
