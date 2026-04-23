import { CircularProgress } from '@mui/material';

import { Button } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelExportConfiguration,
  labelGeneratingCommand
} from '../../../translatedLabels';
import { isGeneratedAtom } from './atoms';
import type { CloudInstallCommandFormValues } from './models';
import {
  useCloudInstallCommand,
  useValidatePoller
} from './useCloudInstallCommand';

const Buttons = (): ReactElement => {
  const { t } = useTranslation();
  const isGenerated = useAtomValue(isGeneratedAtom);
  const { isSubmitting, isValid, submitForm } =
    useFormikContext<CloudInstallCommandFormValues>();
  const { validate, isExporting } = useValidatePoller();
  const { close } = useCloudInstallCommand();

  if (isGenerated) {
    return (
      <div className="flex justify-end gap-2">
        <Button onClick={close} size="medium" variant="ghost">
          {t(labelCancel)}
        </Button>
        <Button disabled={isExporting} onClick={validate} size="medium">
          {t(labelExportConfiguration)}
        </Button>
      </div>
    );
  }

  return (
    <div className="flex justify-end gap-2">
      <Button onClick={close} size="medium" variant="ghost">
        {t(labelCancel)}
      </Button>
      <Button
        data-testid="generate-command"
        disabled={!isValid || isSubmitting}
        onClick={submitForm}
        size="medium"
      >
        {isSubmitting ? (
          <div className="flex items-center gap-2">
            <CircularProgress color="inherit" size={16} />
            {t(labelGeneratingCommand)}
          </div>
        ) : (
          t(labelExportConfiguration)
        )}
      </Button>
    </div>
  );
};

export default Buttons;
