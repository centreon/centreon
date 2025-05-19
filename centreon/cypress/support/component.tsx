import './commands';
import '@cypress/code-coverage/support';

import enableVisualTesting from '@centreon/js-config/cypress/component/enableVisualTesting';
import '../../packages/ui/src/ThemeProvider/tailwindcss.css';

enableVisualTesting();
