const isServing = process.env.WEBPACK_ENV === 'serve';

const plugins = isServing ? ['react-refresh/babel'] : [];

module.exports = {
<<<<<<< HEAD
  extends:
    'centreon-frontend/packages/frontend-config/babel/typescript',
=======
  extends: '@centreon/centreon-frontend/packages/frontend-config/babel/typescript',
>>>>>>> centreon/dev-21.10.x
  plugins,
};
