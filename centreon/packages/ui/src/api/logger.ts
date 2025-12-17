export const errorLog = (text: string): void => {
  const { error: log } = console;

  log(`API Request: ${text}`);
};

export const warnLog = (text: string): void => {
  const { warn: log } = console;

  log(`API Request: ${text}`);
};
