import { startStack, stopStack } from './dockerStack';

const [command] = process.argv.slice(2);

const run = async (): Promise<void> => {
  if (command === 'up') {
    await startStack();

    return;
  }

  if (command === 'down') {
    stopStack();

    return;
  }

  throw new Error(`Unknown command "${command}". Expected "up" or "down".`);
};

run().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
