import { JsonDecoder } from 'ts.data.json';

interface InstallCommand {
  command: string;
}

export const installCommandDecoder = JsonDecoder.object<InstallCommand>(
  {
    command: JsonDecoder.string
  },
  'InstallCommand'
);
