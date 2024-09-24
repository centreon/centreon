const { exec } = require('child_process');
const { readdirSync } = require('fs');
const { replace } = require('ramda');

const getWidgets = () => {
  const widgets = process.argv[3]?.split(',') || [];

  return readdirSync('./src', { withFileTypes: true })
    .filter((value) => value.isDirectory())
    .map(({ name }) => name)
    .filter((name) =>
      name !== 'node_modules' && widgets.length > 0 ? widgets.includes(replace('centreon-widget-', '', name)) : name.match(/^centreon-widget/)
    ).filter((name) => name === "centreon-widget-singlemetric" || name === "centreon-widget-graph" || name === "centreon-widget-topbottom" || name === "centreon-widget-resourcestable");
};

const getWebpackBuildConfiguration = () => {
  const buildMode = process.argv[2];

  if (buildMode === 'development') {
    return {
      config: 'rspack.config.dev.js',
      mode: 'development',
      watch: false,
      analyze: false
    };
  }

  if (buildMode === 'watch') {
    return {
      config: 'rspack.config.dev.js',
      mode: 'development',
      watch: true,
      analyze: false
    };
  }

  if (buildMode === 'analyze') {
    return {
      config: 'rspack.config.js',
      mode: 'production',
      watch: false,
      analyze: true,
    };
  }

  return {
    config: 'rspack.config.js',
    mode: 'production',
    watch: false,
    analyze: false
  };
};

getWidgets().forEach((widgetName) => {
  const { config, mode, watch, analyze } = getWebpackBuildConfiguration();
  console.log(`Bundling ${widgetName} in ${mode}...`);
  exec(
    `rspack build -m ${mode} -c ./${config} --env widgetName=${widgetName} ${
      watch ? '-w' : ''
    } ${
      analyze ? '--analyze' : ''
    }`,
    (error, stdout) => {
      if (error) {
        console.error(`${widgetName}: ${error}`);
      }

      console.log(`${widgetName}: ${stdout}`);
    }
  );
});
