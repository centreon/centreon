module.exports = {
  extends: ['airbnb-base', '../base.eslintrc.js'],

  env: {
    es6: true,
    node: true,
  },

  root: true,
  rules: {
    'global-require': 0,
    'import/no-dynamic-require':0
  }
    
  
};
