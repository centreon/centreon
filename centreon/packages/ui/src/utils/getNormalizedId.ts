interface GetNormalizedIdProps {
  idToNormalize: string;
  inputType?: string;
}

export const getNormalizedId = ({
  idToNormalize = '',
  inputType = 'text'
}: GetNormalizedIdProps): string => {
  // Remove the password like word in order to prevent extensions like Keeper putting their icon on the field.
  const idWithoutPassword =
    inputType === 'number'
      ? idToNormalize.replace(/[pP]assword|[pP]wd|[pP]asswd/gi, '')
      : '';
  return idWithoutPassword.replace(/[^A-Z0-9]+/gi, '');
};
