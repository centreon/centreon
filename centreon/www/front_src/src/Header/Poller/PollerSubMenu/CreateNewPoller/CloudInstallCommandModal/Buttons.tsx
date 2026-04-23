import { Button } from '@centreon/ui/components';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { useCloudInstallCommand } from './useCloudInstallCommand';

import {
  labelCancel,
  labelExportConfiguration
} from '../../../translatedLabels';

const Buttons = (): ReactElement => {
  const { t } = useTranslation();
  const { close } = useCloudInstallCommand();

  return (
    <div className="flex justify-end gap-2">
      <Button onClick={close} size="medium" variant="ghost">
        {t(labelCancel)}
      </Button>
      <Button
        data-testid="generate-command"
        disabled={true}
        onClick={() => undefined}
        size="medium"
      >
        {t(labelExportConfiguration)}
      </Button>
    </div>
  );
};

export default Buttons;
