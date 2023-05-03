import { FC, useState } from 'react';

import { find, isNil, map, path, pathEq } from 'ramda';
import { useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import { Button } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';

import { Dialog, SelectField } from '@centreon/ui';

import {
  labelAdd,
  labelAddAWidget,
  labelAddWidget,
  labelCancel
} from '../translatedLabels';
import useFederatedWidgets from '../../federatedModules/useFederatedWidgets';
import { FederatedModule } from '../../federatedModules/models';
import { addPanelDerivedAtom } from '../atoms';

const useStyles = makeStyles()((theme) => ({
  selectField: {
    minWidth: theme.spacing(20)
  }
}));

const AddWidgetDialog: FC = () => {
  const { classes } = useStyles();

  const [addWidgetDialogOpened, setAddWidgetDialogOpened] = useState(false);
  const [selectedPanel, setSelectedPanel] = useState<FederatedModule | null>(
    null
  );

  const addPanel = useSetAtom(addPanelDerivedAtom);

  const { federatedWidgets } = useFederatedWidgets();

  const open = (): void => setAddWidgetDialogOpened(true);

  const close = (): void => {
    setAddWidgetDialogOpened(false);
    setSelectedPanel(null);
  };

  const confirm = (): void => {
    if (isNil(selectedPanel)) {
      return;
    }

    addPanel({
      options: undefined,
      panelConfiguration: {
        panelMinHeight:
          selectedPanel?.federatedComponentsConfiguration.panelMinHeight,
        panelMinWidth:
          selectedPanel?.federatedComponentsConfiguration.panelMinWidth,
        path: selectedPanel?.federatedComponentsConfiguration.path
      }
    });
    close();
  };

  const selectPanel = (event): void => {
    const value = path(['target', 'value'], event);

    const panel = find(
      pathEq(['federatedComponentsConfiguration', 'path'], value),
      federatedWidgets || []
    );

    setSelectedPanel(panel || null);
  };

  const widgetsAvailable = map(
    ({ moduleName, federatedComponentsConfiguration }) => ({
      id: federatedComponentsConfiguration?.path,
      name: moduleName
    }),
    federatedWidgets || []
  );

  return (
    <>
      <Button size="small" startIcon={<AddIcon />} onClick={open}>
        {labelAddWidget}
      </Button>
      <Dialog
        confirmDisabled={isNil(selectPanel)}
        labelCancel={labelCancel}
        labelConfirm={labelAdd}
        labelTitle={labelAddAWidget}
        open={addWidgetDialogOpened}
        onCancel={close}
        onClose={close}
        onConfirm={confirm}
      >
        <SelectField
          ariaLabel={labelAddAWidget}
          className={classes.selectField}
          dataTestId={labelAddAWidget}
          options={widgetsAvailable}
          selectedOptionId={
            selectedPanel?.federatedComponentsConfiguration?.path || ''
          }
          onChange={selectPanel}
        />
      </Dialog>
    </>
  );
};

export default AddWidgetDialog;
