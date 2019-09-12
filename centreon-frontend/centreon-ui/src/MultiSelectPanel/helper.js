export default (array) => {
  const result = [];
  if (array) {
    for (let i = 0; i < array.length; i++) {
      const separatedString = array[i].split(':');
      result.push({
        id: separatedString[0],
        name: separatedString[1],
      });
    }
  }
  return result;
};
