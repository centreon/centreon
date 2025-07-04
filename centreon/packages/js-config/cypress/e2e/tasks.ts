/* eslint-disable no-console */
import { execSync } from 'child_process';
import { existsSync, mkdirSync } from 'fs';
import path from 'path';
import fs from "fs";

import tar from 'tar-fs';
import {
  DockerComposeEnvironment,
  GenericContainer,
  StartedDockerComposeEnvironment,
  StartedTestContainer,
  Wait,
  getContainerRuntimeClient
} from 'testcontainers';
import { createConnection } from 'mysql2/promise';

interface Containers {
  [key: string]: StartedTestContainer;
}

export default (on: Cypress.PluginEvents): void => {
  let dockerEnvironment: StartedDockerComposeEnvironment | null = null;
  const containers: Containers = {};

  const getContainer = (containerName): StartedTestContainer => {
    let container;

    if (dockerEnvironment !== null) {
      container = dockerEnvironment.getContainer(`${containerName}-1`);
    } else if (containers[containerName]) {
      container = containers[containerName];
    } else {
      throw new Error(`Cannot get container ${containerName}`);
    }

    return container;
  };

  interface PortBinding {
    destination: number;
    source: number;
  }

  interface StartContainerProps {
    command?: string;
    image: string;
    name: string;
    portBindings: Array<PortBinding>;
  }

  interface StopContainerProps {
    name: string;
  }

  on("task", {
    copyFromContainer: async ({ destination, serviceName, source }) => {
      try {
        const container = getContainer(serviceName);

        await container
          .copyArchiveFromContainer(source)
          .then((archiveStream) => {
            return new Promise<void>((resolve) => {
              const dest = tar.extract(destination);
              archiveStream.pipe(dest);
              dest.on("finish", resolve);
            });
          });
      } catch (error) {
        console.error(error);
      }

      return null;
    },
    copyToContainer: async ({ destination, serviceName, source, type }) => {
      const container = getContainer(serviceName);

      if (type === "directory") {
        await container.copyDirectoriesToContainer([
          {
            source,
            target: destination,
          },
        ]);
      } else if (type === "file") {
        await container.copyFilesToContainer([
          {
            source,
            target: destination,
          },
        ]);
      }

      return null;
    },
    createDirectory: async (directoryPath: string) => {
      if (!existsSync(directoryPath)) {
        mkdirSync(directoryPath, { recursive: true });
      }

      return null;
    },
    execInContainer: async ({ command, name }) => {
      const { exitCode, output } = await getContainer(name).exec([
        "bash",
        "-c",
        `${command}${command.match(/[\n\r]/) ? "" : " 2>&1"}`,
      ]);

      return { exitCode, output };
    },
    getContainerId: (containerName: string) =>
      getContainer(containerName).getId(),
    getContainerIpAddress: (containerName: string) => {
      const container = getContainer(containerName);

      const networkNames = container.getNetworkNames();

      return container.getIpAddress(networkNames[0]);
    },
    getContainersLogs: async () => {
      try {
        const { dockerode } = (await getContainerRuntimeClient()).container;
        const loggedContainers = await dockerode.listContainers();

        return loggedContainers.reduce((acc, container) => {
          const containerName = container.Names[0].replace("/", "");
          acc[containerName] = execSync(`docker logs -t ${container.Id}`, {
            stdio: "pipe",
          }).toString("utf8");

          return acc;
        }, {});
      } catch (error) {
        console.warn("Cannot get containers logs");
        console.warn(error);

        return null;
      }
    },
    getContainerMappedPort: async ({ containerName, containerPort }) => {
      const container = getContainer(containerName);

      return container.getMappedPort(containerPort);
    },
    requestOnDatabase: async ({ database, query }) => {
      let container: StartedTestContainer | null = null;

      if (dockerEnvironment !== null) {
        container = dockerEnvironment.getContainer("db-1");
      } else {
        container = getContainer("web");
      }

      const client = await createConnection({
        database,
        host: container.getHost(),
        password: "centreon",
        port: container.getMappedPort(3306),
        user: "centreon",
      });

      const [rows, fields] = await client.query(query);

      await client.end();

      return [rows, fields];
    },
    startContainer: async ({
      command,
      image,
      name,
      portBindings = [],
    }: StartContainerProps) => {
      let container = await new GenericContainer(image).withName(name);

      portBindings.forEach(({ source, destination }) => {
        container = container.withExposedPorts({
          container: source,
          host: destination,
        });
      });

      if (command) {
        container
          .withCommand(["bash", "-c", command])
          .withWaitStrategy(Wait.forSuccessfulCommand("ls"));
      }

      containers[name] = await container.start();

      return container;
    },
    startContainers: async ({
      composeFile,
      databaseImage,
      openidImage,
      profiles,
      samlImage,
      webImage,
    }) => {
      try {
        const composeFileDir = path.dirname(composeFile);
        const composeFileName = path.basename(composeFile);

        dockerEnvironment = await new DockerComposeEnvironment(
          composeFileDir,
          composeFileName,
        )
          .withEnvironment({
            MYSQL_IMAGE: databaseImage,
            OPENID_IMAGE: openidImage,
            SAML_IMAGE: samlImage,
            WEB_IMAGE: webImage,
          })
          .withProfiles(...profiles)
          .withStartupTimeout(120000)
          .withWaitStrategy(
            "web-1",
            Wait.forAll([
              Wait.forHealthCheck(),
              Wait.forLogMessage("Centreon is ready"),
            ]),
          )
          .up();

        return null;
      } catch (error) {
        if (error instanceof Error) {
          console.error(error.message);
        }

        throw error;
      }
    },
    stopContainer: async ({ name }: StopContainerProps) => {
      if (containers[name]) {
        const container = containers[name];

        await container.stop();

        delete containers[name];
      }

      return null;
    },
    stopContainers: async () => {
      if (dockerEnvironment !== null) {
        await dockerEnvironment.down();

        dockerEnvironment = null;
      }

      return null;
    },
    waitOn: async (url: string) => {
      execSync(`npx wait-on ${url}`);

      return null;
    },
    listFilesInDirectory: async ( directoryPath ) => {
      return new Promise((resolve, reject) => {
        fs.readdir(directoryPath, (err, files) => {
          if (err) {
            reject(err);
          } else {
            resolve(files);
          }
        });
      });
    },
    fileExists: async ( filePath ) => {
      return fs.existsSync(filePath);
    },
    getExportedFile({ downloadsFolder }: { downloadsFolder: string }): string {
      const files = fs
        .readdirSync(downloadsFolder)
        .filter((name) => name.startsWith("ResourceStatusExport_all") && name.endsWith(".csv"))
        .map((name) => ({
          name,
          time: fs.statSync(path.join(downloadsFolder, name)).mtime.getTime()
        }))
        .sort((a, b) => b.time - a.time);

      if (files.length === 0) {
        throw new Error("No exported file found");
      }

      return path.join(downloadsFolder, files[0].name);
    },
    readCsvFile({ filePath }: { filePath: string }): Promise<string> {
      return new Promise((resolve, reject) => {
        fs.readFile(filePath, "utf8", (err, data) => {
          if (err) return reject(err);
          resolve(data);
        });
      });
    },
    clearDownloadsFolder({ downloadsFolder }: { downloadsFolder: string }): null {
      if (!fs.existsSync(downloadsFolder)) {
        return null;
      }

      const files = fs.readdirSync(downloadsFolder);
      for (const file of files) {
        const filePath = path.join(downloadsFolder, file);
        fs.unlinkSync(filePath);
      }

      return null;
    },
    isDownloadComplete({ downloadsFolder }: { downloadsFolder: string }): boolean {
      if (!fs.existsSync(downloadsFolder)) return false;

      const files = fs
        .readdirSync(downloadsFolder)
        .filter(
          (name) => !name.endsWith(".crdownload") && !name.endsWith(".tmp")
        );

      return files.length > 0;
    },
  });
};
