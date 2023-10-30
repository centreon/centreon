module.exports = {
  env: {
    es6: true,
    node: true,
  },

  extends: ['./index.eslintrc.js', '../base.typescript.eslintrc.js'],

  rules: {
    'global-require': 0,
    'import/no-dynamic-require': 0,
  },
};
