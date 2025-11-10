export const commandsEndpoint = '/configuration/commands';

export const getCommandsEndpoint = ({ id }): string =>
  `${commandsEndpoint}/${id}`;
