import { useState } from 'react';

import type { Resource } from '../../../../models';
import AddCommentForm from '.';

const date = new Date('2020-11-26T15:49:39.789Z');

const resource = {
  id: 0,
  parent: {
    id: 1
  },
  type: 'service'
} as Resource;

// The story owns the callbacks and records their effect: onSuccess is called
// with the sent parameters, onClose only increments a counter.
export const Default = (): JSX.Element => {
  const [successPayload, setSuccessPayload] = useState('');
  const [closeCount, setCloseCount] = useState(0);

  return (
    <>
      <AddCommentForm
        date={date}
        onClose={() => setCloseCount((count) => count + 1)}
        onSuccess={(comment) => setSuccessPayload(JSON.stringify(comment))}
        resource={resource}
      />
      <form hidden>
        <input data-testid="close-count" readOnly value={String(closeCount)} />
        <input data-testid="success-payload" readOnly value={successPayload} />
      </form>
    </>
  );
};
