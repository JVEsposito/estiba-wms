const baseConfig = require('./app.json').expo;

const demoBuild = process.env.ESTIBA_APP_VARIANT === 'demo';
const pdaBuild = process.env.ESTIBA_APP_VARIANT === 'pda';

// APK de mano para Unitech EA520 (5", 1280×720, Android 11): solo Validación PT
// y MP, siempre en vertical. Es otra aplicación Android (otro paquete), de modo
// que puede convivir con la APK de tablet y recibe actualizaciones por su
// propio canal EAS `pda`.
function pdaConfig() {
  return {
    ...baseConfig,
    name: 'FoliOS PDA',
    orientation: 'portrait',
    android: {
      ...baseConfig.android,
      package: 'cl.estiba.wms.pda',
      versionCode: 1,
      adaptiveIcon: {
        ...baseConfig.android.adaptiveIcon,
        backgroundColor: '#0F5C6E',
      },
    },
    extra: {
      ...baseConfig.extra,
      appVariant: 'pda',
    },
  };
}

function demoPlugins() {
  return baseConfig.plugins.map((plugin) => {
    if (!Array.isArray(plugin) || plugin[0] !== 'expo-build-properties') return plugin;

    return [
      plugin[0],
      {
        ...plugin[1],
        android: {
          ...plugin[1].android,
          usesCleartextTraffic: false,
        },
      },
    ];
  });
}

module.exports = () => {
  if (pdaBuild) return pdaConfig();
  if (!demoBuild) return baseConfig;

  return {
    ...baseConfig,
    name: 'FoliOS Demo',
    version: '1.1.0',
    updates: {
      enabled: false,
      checkAutomatically: 'NEVER',
      fallbackToCacheTimeout: 0,
    },
    android: {
      ...baseConfig.android,
      package: 'cl.estiba.wms.demo',
      versionCode: 2,
      adaptiveIcon: {
        ...baseConfig.android.adaptiveIcon,
        backgroundColor: '#183442',
      },
    },
    plugins: demoPlugins(),
    extra: {
      ...baseConfig.extra,
      appVariant: 'demo',
      demoOnly: true,
    },
  };
};
