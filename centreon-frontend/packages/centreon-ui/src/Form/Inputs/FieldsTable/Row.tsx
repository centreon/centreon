import { FormikValues, useFormikContext } from 'formik';
import { not, path, split, remove } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import DeleteIcon from '@mui/icons-material/Delete';

import { IconButton } from '../../..';
import { getInput } from '..';
import { InputPropsWithoutGroup } from '../models';

interface StylesProps {
  columns?: number;
}

const useStyles = makeStyles<StylesProps>()((theme, { columns }) => ({
  icon: {
    marginTop: theme.spacing(0.5),
  },
  inputsRow: {
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(${columns}, 1fr) ${theme.spacing(6)}`,
    gridTemplateRows: 'min-content',
  },
}));

interface Props {
  columns?: Array<InputPropsWithoutGroup>;
  defaultRowValue?: object | string;
  deleteLabel?: string;
  getRequired: () => boolean;
  hasSingleValue?: boolean;
  index: number;
  isLastElement: boolean;
  label: string;
  tableFieldName: string;
}

const Row = ({
  label,
  index,
  columns,
  tableFieldName,
  defaultRowValue,
  getRequired,
  isLastElement,
  deleteLabel,
  hasSingleValue,
}: Props): JSX.Element => {
  const { classes } = useStyles({ columns: columns?.length });
  const { t } = useTranslation();

  const { setFieldValue, values } = useFormikContext<FormikValues>();

  const tableFieldNamePath = split('.', tableFieldName);

  const tableValues = path(tableFieldNamePath, values) as Array<unknown>;
  const rowValues = tableValues[index];

  const deleteRow = (): void => {
    setFieldValue(tableFieldName, remove(index, 1, tableValues));
  };

  const changeRow = ({ property, value }): void => {
    const currentRowValue = rowValues || defaultRowValue;

    setFieldValue(
      `${tableFieldName}.${index}`,
      hasSingleValue
        ? value
        : {
            ...currentRowValue,
            [property]: value,
          },
    );
  };

  return (
    <div className={classes.inputsRow} key={`${label}_${index}`}>
      {columns?.map((field): JSX.Element => {
        const Input = getInput(field.type);

        const inputFieldName = hasSingleValue
          ? `${tableFieldName}.${index}`
          : `${tableFieldName}.${index}.${field.fieldName}`;

        return (
          <Input
            {...field}
            additionalMemoProps={[rowValues]}
            change={({ value }): void =>
              changeRow({
                property: field.fieldName,
                value,
              })
            }
            fieldName={inputFieldName}
            getRequired={getRequired}
            key={`${label}_${index}_${field.label}`}
          />
        );
      })}
      {not(isLastElement) && (
        <IconButton
          ariaLabel={deleteLabel && t(deleteLabel)}
          className={classes.icon}
          title={deleteLabel && t(deleteLabel)}
          onClick={deleteRow}
        >
          <DeleteIcon />
        </IconButton>
      )}
    </div>
  );
};

export default Row;
