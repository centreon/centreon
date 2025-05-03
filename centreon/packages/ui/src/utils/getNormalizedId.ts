interface GetNormalizedIdProps {
  idToNormalize: string;
  inputType?: string;
}

export const getNormalizedId = ({
  idToNormalize = '',
  inputType = 'text'
}: GetNormalizedIdProps): string => {
  const idWithoutPassword =
    inputType === 'number'
      ? idToNormalize.replace(/[pP]assword|[pP]wd|[pP]asswd/gi, '')
      : '';
  return idWithoutPassword.replace(/[^A-Z0-9]+/gi, '');
};
