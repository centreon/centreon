import {
  Paper,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow
} from '@mui/material';

import type { Column, ColumnType, ListingModel } from '@centreon/ui';
import { getData, useRequest } from '@centreon/ui';

import { map, pipe, prop, sum } from 'ramda';
import { ReactElement, useEffect, useState } from 'react';

import {
  labelNo,
  labelSomethingWentWrong,
  labelYes
} from '../../../../translatedLabels';

const getYesNoLabel = (value: boolean): string => (value ? labelYes : labelNo);

interface DetailsTableColumn extends Column {
  // biome-ignore lint/suspicious/noExplicitAny: typing fallback
  getContent: (details: any) => string | ReactElement;
  id: string;
  label: string;
  type: ColumnType;
  width: number;
}

export interface DetailsTableProps {
  columns: Array<DetailsTableColumn>;
  endpoint: string;
}

const DetailsTable = <TDetails extends { id: number }>({
  endpoint,
  columns
}: DetailsTableProps): ReactElement => {
  const [details, setDetails] = useState<Array<TDetails> | null>();

  const { sendRequest } = useRequest<ListingModel<TDetails>>({
    request: getData as unknown as (
      token: import('axios').CancelToken
    ) => (params?: unknown) => Promise<ListingModel<TDetails>>
  });

  useEffect(() => {
    sendRequest({
      endpoint
    }).then((retrievedDetails) => {
      setDetails(retrievedDetails.result);
    });
  }, []);

  const loading = details === undefined;
  const error = details === null;
  const success = !loading && !error;

  const tableMaxWidth = pipe(
    map<{ width: number }, number>(prop('width')),
    sum
  )(columns);

  return (
    <TableContainer component={Paper}>
      <Table size="small" style={{ width: tableMaxWidth }}>
        <TableHead>
          <TableRow>
            {columns.map(({ label }) => (
              <TableCell key={label}>{label}</TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {loading && (
            <TableRow>
              <TableCell colSpan={columns.length}>
                <Skeleton animation="wave" height={20} />
              </TableCell>
            </TableRow>
          )}
          {success &&
            details?.map((detail) => (
              <TableRow key={detail.id}>
                {columns.map(({ label, getContent, width }) => (
                  <TableCell key={label} style={{ maxWidth: width }}>
                    <span>{getContent?.(detail)}</span>
                  </TableCell>
                ))}
              </TableRow>
            ))}
          {error && (
            <TableRow>
              <TableCell align="center" colSpan={columns.length}>
                <span>{labelSomethingWentWrong}</span>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </TableContainer>
  );
};

export { getYesNoLabel };
export default DetailsTable;
