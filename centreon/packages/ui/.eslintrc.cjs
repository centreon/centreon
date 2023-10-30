module.exports = {
  root: true,
  extends: [
    '../js-config/eslint/react/typescript.eslintrc.js'
  ],
  settings: {
    'import/resolver': {
      alias: {
        map: [
          ['@centreon/ui/fonts', './public/fonts']
        ]
      }
    }
  },
  overrides: [
    {
      // less strict for stories
      "files": ["*.stories.tsx"],
      "rules": {
        "react/no-array-index-key": "warn",
      }
    }
  ]
};