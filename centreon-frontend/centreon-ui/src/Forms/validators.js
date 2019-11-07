const requiredValidator = (event, message) =>
  new Promise((resolve, reject) => {
    const value = event.target ? event.target.value : event;
    if (value.length > 0) {
      resolve();
    } else {
      reject(message);
    }
  });
export { requiredValidator };

export default {
  requiredValidator,
};
