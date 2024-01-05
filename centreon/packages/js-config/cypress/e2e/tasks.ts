import { execSync } from 'child_process';
import { existsSync, mkdirSync } from 'fs';

import Docker from 'dockerode';
import tar from 'tar-fs';
import {
  DockerComposeEnvironment,
  GenericContainer,
  StartedTestContainer,
  Wait
} from 'testcontainers';
import { createConnection } from 'mysql2/promise';

export default (on: Cypress.PluginEvents): void => {
  const docker = new Docker();
  let dockerEnvironment;

  interface PortBinding {
    destination: number;
    source: number;
  }

  interface StartContainerProps {
    image: string;
    name: string;
    portBindings: Array<PortBinding>;
  }

  interface StopContainerProps {
    name: string;
  }

  on('task', {
    copyFromContainer: async ({ destination, serviceName, source }) => {
      const container = dockerEnvironment.getContainer(`${serviceName}-1`);

      await container.copyArchiveFromContainer(source).then((archiveStream) => {
        return new Promise<void>((resolve) => {
          const dest = tar.extract(destination);
          archiveStream.pipe(dest);
          dest.on('finish', resolve);
        });
      });

      return null;
    },
    createDirectory: async (directoryPath: string) => {
      if (!existsSync(directoryPath)) {
        mkdirSync(directoryPath, { recursive: true });
      }

      return null;
    },
    requestOnDatabase: async ({ database, query }) => {
      const container = dockerEnvironment.getContainer('db-1');

      const client = await createConnection({
        database,
        host: container.getHost(),
        password: 'centreon',
        port: container.getMappedPort(3306),
        user: 'centreon'
      });

      const [rows, fields] = await client.execute(query);

      await client.end();

      return [rows, fields];
    },
    startContainer: async ({
      image,
      name,
      portBindings = []
    }: StartContainerProps) => {
      const imageList = execSync(
        'docker image list --format "{{.Repository}}:{{.Tag}}"'
      ).toString('utf8');

      if (
        !imageList.match(
          new RegExp(
            `^${image.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&')}`,
            'm'
          )
        )
      ) {
        execSync(`docker pull ${image}`);
      }

      const webContainers = await docker.listContainers({
        all: true,
        filters: { name: [name] }
      });
      if (webContainers.length) {
        return webContainers[0];
      }

      const container = await docker.createContainer({
        AttachStderr: true,
        AttachStdin: false,
        AttachStdout: true,
        ExposedPorts: portBindings.reduce((accumulator, currentValue) => {
          accumulator[`${currentValue.source}/tcp`] = {};

          return accumulator;
        }, {}),
        HostConfig: {
          PortBindings: portBindings.reduce((accumulator, currentValue) => {
            accumulator[`${currentValue.source}/tcp`] = [
              {
                HostIP: '127.0.0.1',
                HostPort: `${currentValue.destination}`
              }
            ];

            return accumulator;
          }, {})
        },
        Image: image,
        OpenStdin: false,
        StdinOnce: false,
        Tty: true,
        name
      });

      await container.start();

      return container;
    },
    startContainers: async ({
      composeFilePath = `${__dirname}/../../../../../.github/docker/`,
      databaseImage,
      openidImage,
      profiles,
      webImage
    }) => {
      const composeFile = 'docker-compose.yml';

      dockerEnvironment = await new DockerComposeEnvironment(
        composeFilePath,
        composeFile
      )
        .withEnvironment({
          MYSQL_IMAGE: databaseImage,
          OPENID_IMAGE: openidImage,
          WEB_IMAGE: webImage
        })
        .withProfiles(...profiles)
        .withWaitStrategy('web', Wait.forHealthCheck())
        .up();

      return null;
    },
    stopContainer: async ({ name }: StopContainerProps) => {
      const container = await docker.getContainer(name);
      await container.kill();
      await container.remove();

      return null;
    },
    stopContainers: async () => {
      await dockerEnvironment.down();

      return null;
    },
    waitOn: async (url: string) => {
      execSync(`npx wait-on ${url}`);

      return null;
    }
  });
};
