const PLUGIN_NAME = 'ModuleFederationIgnoreEntries';

// This fixes the following issue: https://github.com/webpack/webpack/discussions/14985
// Plugin built by @meskill: https://github.com/Tinkoff/tramvai/blob/main/packages/cli/src/library/webpack/plugins/ModuleFederationIgnoreEntries.ts
export class ModuleFederationIgnoreEntries {
  constructor(entries) {
    this.entries = new Set(entries);
  }

  apply(compiler) {
    compiler.hooks.thisCompilation.tap(PLUGIN_NAME, (compilation) => {
      compilation.hooks.beforeChunks.tap(PLUGIN_NAME, () => {
        const { includeDependencies } = compilation.globalEntry;

        // eslint-disable-next-line no-restricted-syntax
        for (const [entryName, entry] of compilation.entries) {
          if (!this.entries.has(entryName)) {
            entry.includeDependencies.push(...includeDependencies);
          }
        }

        // eslint-disable-next-line no-param-reassign
        compilation.globalEntry.includeDependencies = [];
      });
      compilation.hooks.additionalTreeRuntimeRequirements.intercept({
        register: (tap) => {
          if (tap.name === 'ConsumeSharedPlugin') {
            const originalFn = tap.fn;
            // eslint-disable-next-line no-param-reassign
            tap.fn = (chunk, ...args) => {
              if (!this.entries.has(chunk.name)) {
                originalFn(chunk, ...args);
              }
            };
          }

          return tap;
        }
      });
    });
  }
}
