import { useState } from 'react';

// The exported field, as the Cypress spec used.
import { MultiConnectedAutocompleteField, TestQueryProvider } from '../../../../..';
import { getEndpoint, label, placeholder } from './utils';

export const Default = (): JSX.Element => {
  const [values, setValues] = useState<Array<unknown>>([]);

  return (
    <TestQueryProvider>
      <div style={{ paddingTop: 20 }}>
        <MultiConnectedAutocompleteField
          // Reaches the multi autocomplete, whose default hides select all.
          {...({ disableSelectAll: false } as object)}
          field="host.name"
          getEndpoint={getEndpoint}
          label={label}
          onChange={(_, items) => setValues(items as Array<unknown>)}
          placeholder={placeholder}
          value={values as never}
        />
      </div>
    </TestQueryProvider>
  );
};
