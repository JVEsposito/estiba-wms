const { withGradleProperties } = require('expo/config-plugins');

const PROPERTY = 'AsyncStorage_db_size_in_MB';
const SIZE_IN_MB = '64';

module.exports = function withAsyncStorageSize(config) {
  return withGradleProperties(config, (gradleConfig) => {
    const current = gradleConfig.modResults.find(
      (entry) => entry.type === 'property' && entry.key === PROPERTY,
    );

    if (current) {
      current.value = SIZE_IN_MB;
    } else {
      gradleConfig.modResults.push({
        type: 'property',
        key: PROPERTY,
        value: SIZE_IN_MB,
      });
    }

    return gradleConfig;
  });
};
