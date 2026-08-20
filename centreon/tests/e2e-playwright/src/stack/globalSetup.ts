import { platformVersionsUrl, reuseExistingStack } from '../config/environment';
import { startStack } from './dockerStack';

const globalSetup = async (): Promise<void> => {
  if (reuseExistingStack) {
    console.log(`Reusing the stack already running on ${platformVersionsUrl}`);

    return;
  }

  console.log('Starting the centreon-web docker compose stack...');

  await startStack();

  console.log('Centreon platform is ready.');
};

export default globalSetup;
