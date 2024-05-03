const fs = require('fs');

const swc = require('@swc/core');

module.exports = class TransformPreloadScript {
  constructor(federatedComponentConfiguration) {
    this.federatedComponentConfiguration = federatedComponentConfiguration;
  }

  apply(compiler) {
    compiler.hooks.done.tap('TransformPreloadScript', () => {
      if (!fs.existsSync(compiler.options.output.path)) {
        fs.mkdirSync(compiler.options.output.path, { recursive: true });
      }

      if (this.federatedComponentConfiguration.preloadScript) {
        const { code } = swc.transformFileSync(
          `./${this.federatedComponentConfiguration.preloadScript}.ts`,
          {
            filename: `${this.federatedComponentConfiguration.preloadScript}.ts`,
            jsc: {
              parser: {
                syntax: 'typescript'
              }
            },
            minify: true
          }
        );

        fs.writeFileSync(
          `${compiler.options.output.path}/${this.federatedComponentConfiguration.preloadScript}.js`,
          code
        );
      }
    });
  }
};
