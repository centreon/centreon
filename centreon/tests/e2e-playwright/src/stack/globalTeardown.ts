import { keepStackRunning, reuseExistingStack } from '../config/environment';
import { stopStack } from './dockerStack';

const globalTeardown = (): void => {
  if (reuseExistingStack || keepStackRunning) {
    console.log('Leaving the docker compose stack running.');

    return;
  }

  console.log('Stopping the centreon-web docker compose stack...');

  stopStack();
};

export default globalTeardown;
