import axios, { type CancelTokenSource } from 'axios';
import { useState } from 'react';

const useCancelTokenSource = (): CancelTokenSource => {
  const [cancelTokenSource] = useState(axios.CancelToken.source());

  return cancelTokenSource;
};

export default useCancelTokenSource;
