const getNormalizedId = (idToNormalized: string): string => {
  return idToNormalized?.replace(/[^A-Z0-9]+/gi, '');
};

export default getNormalizedId;
