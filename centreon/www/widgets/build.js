const { exec } = require('child_process');
const { readdirSync } = require('fs');
const { replace } = require('ramda');

const getWidgets = () => {
  const widgets = process.argv[3]?.split(',') || [];

  return readdirSync('./src', { withFileTypes: true })
    .filter((value) => value.isDirectory())
    .map(({ name }) => name)
    .filter((name) =>
      name !== 'node_modules' && widgets.length > 0 ? widgets.includes(replace('centreon-widget-', '', name)) : true
    ).filter((name) => name=== "centreon-widget-statusgrid");
};

const getWebpackBuildConfiguration = () => {
  const buildMode = process.argv[2];

  if (buildMode === 'development') {
    return {
      config: 'webpack.config.dev.js',
      mode: 'development',
      watch: false
    };
  }

  if (buildMode === 'watch') {
    return {
      config: 'webpack.config.dev.js',
      mode: 'development',
      watch: true
    };
  }

  if (buildMode === 'analyze') {
    return {
      config: 'webpack.config.analyze.js',
      mode: 'production',
      watch: false
    };
  }

  return {
    config: 'webpack.config.prod.js',
    mode: 'production',
    watch: false
  };
};

getWidgets().forEach((widgetName) => {
  const { config, mode, watch } = getWebpackBuildConfiguration();
  console.log(`Bundling ${widgetName} in ${mode}...`);
  exec(
    `node ./node_modules/webpack/bin/webpack.js --mode ${mode} --config ${config} --env widgetName=${widgetName} ${
      watch ? '--watch' : ''
    }`,
    (error, stdout) => {
      if (error) {
        console.error(error);
      }

      console.log(stdout);
    }
  );
});
