/* eslint-disable array-callback-return */
/* eslint-disable consistent-return */
const config = {
  story: null
};

const argv = process.argv.slice(0, 2);

process.argv.reduce((cmd, arg) => {
  if (cmd) {
    config[cmd] = arg;

    return;
  }

  if (arg.startsWith('--')) {
    const sub = arg.substring('--'.length);
    if (Object.keys(config).includes(sub)) {
      if (typeof config[sub] === 'boolean') {
        config[cmd] = true;

        return;
      }

      return sub;
    }
  }

  argv.push(arg);
});

process.argv = argv;

process.env.TEST_CONFIGURATION = JSON.stringify(config);

require('jest/bin/jest');
