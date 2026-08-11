const baseConfig = require('./app.json').expo;

const demoBuild = process.env.ESTIBA_APP_VARIANT === 'demo';

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
  if (!demoBuild) return baseConfig;

  return {
    ...baseConfig,
    name: 'Estiba WMS Demo',
    version: '1.0.0',
    updates: {
      enabled: false,
      checkAutomatically: 'NEVER',
      fallbackToCacheTimeout: 0,
    },
    android: {
      ...baseConfig.android,
      package: 'cl.estiba.wms.demo',
      versionCode: 1,
      adaptiveIcon: {
        ...baseConfig.android.adaptiveIcon,
        backgroundColor: '#6B5835',
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
