import { MoreHoriz as MoreIcon } from '@mui/icons-material';

import { Button } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { labelMoreActions } from '../../../translatedLabels';
import { selectedRowsAtom } from '../../atoms';
import { useActionsStyles } from '../Actions.styles';
import MoreActions from './MoreActions';

const MassiveActions = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles({});

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const selectedRows = useAtomValue(selectedRowsAtom);
  const selectedRowsIds = selectedRows?.map((row) => row.id);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  return (
    <div className={classes.actions}>
      <Button
        aria-label={t(labelMoreActions)}
        data-testid={labelMoreActions}
        icon={<MoreIcon />}
        iconVariant="start"
        size="small"
        variant="ghost"
        onClick={openMoreActions}
        disabled={isEmpty(selectedRowsIds)}
      >
        <div className={classes.moreActions}>{t(labelMoreActions)}</div>
      </Button>

      <MoreActions anchor={moreActionsOpen} close={closeMoreActions} />
    </div>
  );
};

export default MassiveActions;
