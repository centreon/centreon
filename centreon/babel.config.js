const isServing = process.env.WEBPACK_ENV === 'serve';

const plugins = isServing ? ['react-refresh/babel'] : [];

module.exports = {
  extends: '@centreon/js-config/babel/typescript',
  plugins,
};
