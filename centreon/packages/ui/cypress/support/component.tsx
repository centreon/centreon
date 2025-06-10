import './commands';
import '@cypress/code-coverage/support';

import enableVisualTesting from '@centreon/js-config/cypress/component/enableVisualTesting';
import '../../src/ThemeProvider/tailwindcss.css';

enableVisualTesting();
