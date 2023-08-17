> The assets in `public` are served as-is.


---

### `public/fonts` → `@centreon/ui/fonts` alias

The `ThemeProvider` specifies the `@font-face` for MUI.
It imports the fonts using the `@centreon/ui/fonts` alias, but does not bundle them.



#### ⚠️ **note** 
When the `@centreon/ui` package is used as non-build dependency, then the `@centreon/ui/fonts` alias need to be added in the webpack config, and configured to handle the asset types.
```js
// webpack.config.js

// alias
resolve: {
  alias: {
    '@centreon/ui/fonts': path.resolve(
      './node_modules/@centreon/ui/public/fonts'
    )
  }
}

// asset types
module: {
  rules: [
    {
      generator: {
          filename: '[name][ext]'
      },
      test: /\.(woff|woff2|eot|ttf|otf)$/i,
      type: 'asset/resource'
    }
  ]
}
```
