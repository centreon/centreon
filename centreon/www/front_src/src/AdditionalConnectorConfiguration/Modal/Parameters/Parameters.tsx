/* eslint-disable react/no-array-index-key */
import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';

import { TextField } from '@centreon/ui';
import { ItemComposition } from '@centreon/ui/components';

import {
  labelDelete,
  labelAddParameter,
  labelValue,
  labelName
} from '../../translatedLabels';

import { useParametersStyles } from './useParametersStyles';

const Parameters = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useParametersStyles();

  const addParameter = (): void => undefined;

  const deleteButtonHidden = true;
  const addbuttonDisabled = false;

  const datasetFilter = [
    { name: 'vcenter name', value: '' },
    { name: 'url', value: '' },
    { name: 'username', value: '' },
    { name: 'password', value: '' }
  ];

  return (
    <div className={classes.resourceComposition}>
      <ItemComposition
        IconAdd={<AddIcon />}
        addbuttonDisabled={addbuttonDisabled}
        labelAdd={t(labelAddParameter)}
        onAddItem={addParameter}
      >
        {datasetFilter.map((_, index) => (
          <div className={classes.resourceCompositionItem} key={index}>
            <ItemComposition.Item
              className={classes.resourceDataset}
              deleteButtonHidden={deleteButtonHidden}
              key={index}
              labelDelete={t(labelDelete)}
              onDeleteItem={() => undefined}
            >
              <TextField
                fullWidth
                dataTestId={labelName}
                placeholder={t(labelName)}
                value={undefined}
                onChange={() => undefined}
              />
              <TextField
                fullWidth
                dataTestId={labelValue}
                placeholder={t(labelValue)}
                value={undefined}
                onChange={() => undefined}
              />
            </ItemComposition.Item>
          </div>
        ))}
      </ItemComposition>
      {/* {error && <FormHelperText error>{t(error)}</FormHelperText>} */}
    </div>
  );
};

export default Parameters;
