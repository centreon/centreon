import { FC } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useAtomValue, useSetAtom } from 'jotai';

import { Card, CardHeader } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { IconButton, useMemoComponent } from '@centreon/ui';

import {
  duplicateWidgetDerivedAtom,
  getWidgetOptionsDerivedAtom,
  isEditingAtom,
  removeWidgetDerivedAtom,
  setWidgetOptionsDerivedAtom
} from '../atoms';
import FederatedComponent from '../../components/FederatedComponents';

interface Props {
  path: string;
  title: string;
}

const useStyles = makeStyles()((theme) => ({
  widgetActionsIcons: {
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row'
  },
  widgetContainer: {
    height: '100%',
    width: '100%'
  },
  widgetContent: {
    padding: theme.spacing(1, 2)
  },
  widgetHeader: {
    padding: theme.spacing(1, 2)
  }
}));

const Widget: FC<Props> = ({ title, path }) => {
  const { classes } = useStyles();

  const isEditing = useAtomValue(isEditingAtom);
  const getWidgetOptions = useAtomValue(getWidgetOptionsDerivedAtom);
  const removeWidget = useSetAtom(removeWidgetDerivedAtom);
  const duplicateWidget = useSetAtom(duplicateWidgetDerivedAtom);
  const setWidgetOptions = useSetAtom(setWidgetOptionsDerivedAtom);

  const remove = (event): void => {
    event.preventDefault();

    removeWidget(title);
  };

  const duplicate = (event): void => {
    event.preventDefault();

    duplicateWidget(title);
  };

  const widgetOptions = getWidgetOptions(title);

  const changeWidgetOptions = (newWidgetOptions): void => {
    setWidgetOptions({ options: newWidgetOptions, title });
  };

  return useMemoComponent({
    Component: (
      <Card className={classes.widgetContainer}>
        <CardHeader
          action={
            isEditing && (
              <div className={classes.widgetActionsIcons}>
                <IconButton onClick={duplicate}>
                  <ContentCopyIcon fontSize="small" />
                </IconButton>
                <IconButton onClick={remove}>
                  <CloseIcon fontSize="small" />
                </IconButton>
              </div>
            )
          }
          className={classes.widgetHeader}
        />
        <div className={classes.widgetContent}>
          <FederatedComponent
            isFederatedWidget
            memoProps={[widgetOptions, title]}
            path={path}
            setWidgetOptions={changeWidgetOptions}
            title={title}
            widgetOptions={widgetOptions}
          />
        </div>
      </Card>
    ),
    memoProps: [isEditing, title, widgetOptions]
  });
};

export default Widget;
