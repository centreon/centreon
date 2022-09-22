import { FormikValues, useFormikContext } from 'formik';
import { dec, equals, inc, isNil, pick, split, path, type } from 'ramda';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { FormHelperText, Typography } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { useMemoComponent } from '../../..';
import { InputPropsWithoutGroup } from '../models';

import Row from './Row';

interface StylesProps {
  columns?: number;
}

const useStyles = makeStyles<StylesProps>()((theme, { columns }) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(1),
  },
  icon: {
    marginTop: theme.spacing(0.5),
  },
  inputsRow: {
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(${columns}, 1fr) min-content`,
  },
  table: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(2),
  },
}));

const FieldsTable = ({
  fieldsTable,
  fieldName,
  label,
}: InputPropsWithoutGroup): JSX.Element => {
  const { classes } = useStyles({
    columns: fieldsTable?.columns.length,
  });
  const { t } = useTranslation();

  const { themeMode } = useAtomValue(userAtom);

  const { values, errors } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const tableValues = path(fieldNamePath, values) as Array<unknown>;

  const fieldsTableError = path(fieldNamePath, errors) as string | undefined;

  const fieldsToMemoize = pick(
    fieldsTable?.additionalFieldsToMemoize || [],
    values,
  );

  const createNewRow = isNil(fieldsTableError);

  const fieldsTableRows = createNewRow
    ? inc(tableValues.length)
    : tableValues.length;

  return useMemoComponent({
    Component: (
      <div className={classes.container}>
        <Typography>{t(label)}</Typography>
        <div className={classes.table}>
          {[...Array(fieldsTableRows).keys()].map((idx): JSX.Element => {
            const getRequired = (): boolean =>
              fieldsTable?.getRequired?.({ index: idx, values }) || false;

            const isLastElement = equals(idx, dec(fieldsTableRows));

            return (
              <Row
                columns={fieldsTable?.columns}
                defaultRowValue={fieldsTable?.defaultRowValue}
                deleteLabel={fieldsTable?.deleteLabel}
                getRequired={getRequired}
                hasSingleValue={fieldsTable?.hasSingleValue}
                index={idx}
                isLastElement={isLastElement}
                key={`${label}_${idx}`}
                label={label}
                tableFieldName={fieldName}
              />
            );
          })}
        </div>
        {equals(type(fieldsTableError), 'String') && (
          <FormHelperText error>{fieldsTableError}</FormHelperText>
        )}
      </div>
    ),
    memoProps: [tableValues, fieldsTableError, themeMode, fieldsToMemoize],
  });
};

export default FieldsTable;
