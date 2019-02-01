export default (data, dispatch, requestType) => {
  return new Promise((resolve, reject) => {
    dispatch({
      type: `@axios/${requestType}_DATA`,
      ...data,
      resolve,
      reject
    });
  });
};
