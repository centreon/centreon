import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { getExportConfigEndpoint } from '../../../../../api/endpoints';
import {
  labelCancel,
  labelConfigurationExportedAndReloaded,
  labelExportConfiguration,
  labelFailedToExportAndReloadConfiguration,
  labelPleaseWait
} from '../../../../translatedLabels';
import { isGeneratedAtom, pollerIdAtom } from '../../atoms';

interface Props {
  close: () => void;
}

const Buttons = ({ close }: Props): ReactElement => {
  const { t } = useTranslation();
  const isCommandGenerated = useAtomValue(isGeneratedAtom);
  const pollerId = useAtomValue(pollerIdAtom);

  const { showSuccessMessage } = useSnackbar();

  const { mutate, isMutating } = useMutationQuery({
    defaultFailureMessage: t(labelFailedToExportAndReloadConfiguration),
    getEndpoint: () => getExportConfigEndpoint(pollerId as number),
    method: Method.GET,
    onSuccess: () => {
      showSuccessMessage(t(labelConfigurationExportedAndReloaded));
      close();
    }
  });

  return (
    <div className="flex justify-end gap-2">
      <Button
        disabled={isMutating}
        onClick={close}
        size="medium"
        variant="ghost"
      >
        {t(labelCancel)}
      </Button>
      <Button
        data-testid="generate-command"
        disabled={isMutating || !isCommandGenerated || isNil(pollerId)}
        onClick={(): void => {
          mutate({});
        }}
        size="medium"
      >
        {t(isMutating ? labelPleaseWait : labelExportConfiguration)}
      </Button>
    </div>
  );
};

export default Buttons;
