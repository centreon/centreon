// Command arguments are typed as legacy shows them, `!arg1!arg2`, where the
// API takes and returns the list.

// The way the server stores them: each argument behind a `!`.
export const argumentsToText = (args: Array<string>): string =>
  args.map((argument) => `!${argument}`).join('');

// The leading `!` is optional, so `a!b` and `!a!b` are the same two arguments.
export const textToArguments = (text: string): Array<string> => {
  if (text === '') {
    return [];
  }

  const [first, ...rest] = text.split('!');

  return first === '' ? rest : [first, ...rest];
};
