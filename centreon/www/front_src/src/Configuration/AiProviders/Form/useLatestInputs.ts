import { useEffect, useRef } from 'react';

// Guards a call the form sends with the values being edited: its result only
// applies while it is the latest call, the form is still open on it and the
// inputs it was sent with are unchanged. `inputs` serializes those inputs.
export const useLatestInputs = (inputs: string): (() => () => boolean) => {
  const inputsRef = useRef(inputs);
  const requestIdRef = useRef(0);

  useEffect(() => {
    inputsRef.current = inputs;
  }, [inputs]);

  // closing the form, or opening another provider, drops a pending result
  useEffect(
    () => () => {
      requestIdRef.current += 1;
    },
    []
  );

  return () => {
    requestIdRef.current += 1;

    const id = requestIdRef.current;
    const sentInputs = inputsRef.current;

    return (): boolean =>
      id === requestIdRef.current && sentInputs === inputsRef.current;
  };
};
