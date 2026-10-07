// Typed as legacy shows them, `!arg1!arg2`; the API takes and returns the list.
export const argumentsToText = (args: Array<string>): string =>
  args.map((argument) => `!${argument}`).join('');

// The leading `!` is optional.
export const textToArguments = (text: string): Array<string> => {
  if (text === '') {
    return [];
  }

  const [first, ...rest] = text.split('!');

  return first === '' ? rest : [first, ...rest];
};
