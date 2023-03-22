import { SetStateAction, useEffect, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { isNil } from 'ramda';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconArrow from '@mui/icons-material/KeyboardArrowDownOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { postData, useRequest, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import {
  labelCheck,
  labelCheckCommandSent,
  labelForcedCheck,
  labelForcedCheckCommandSent
} from '../../../translatedLabels';
import { checkEndpoint } from '../../api/endpoint';
import useAclQuery from '../aclQuery';
import ResourceActionButton from '../ResourceActionButton';

import { adjustCheckedResources } from './helpers';
import ListCheckOptions from './ListCheckOptions';

interface DisplaySelectedOption {
  checked: boolean;
  forcedChecked: boolean;
}

interface Props {
  selectedResources: Array<Resource>;
  setSelectedResources: (update: SetStateAction<Array<Resource>>) => void;
  testId: string;
}

const CheckActionButton = ({
  selectedResources,
  setSelectedResources,
  testId
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const [displaySelectedOption, setDisplaySelectedOption] =
    useState<DisplaySelectedOption | null>(null);
  const { sendRequest: checkResource } = useRequest({ request: postData });

  const { canForcedCheck, canCheck } = useAclQuery();
  const { showSuccessMessage } = useSnackbar();

  const disableCheck = !canCheck(selectedResources);
  const disableForcedCheck = !canForcedCheck(selectedResources);
  const hasSelectedResources = selectedResources.length > 0;

  const isCheckPermitted = canCheck(selectedResources) || !hasSelectedResources;
  const isForcedCheckPermitted =
    canForcedCheck(selectedResources) || !hasSelectedResources;

  const displayPopover = (event: React.MouseEvent<HTMLElement>): void => {
    setAnchorEl(event.currentTarget);
  };

  const closePopover = (): void => {
    setAnchorEl(null);
  };

  const handleCheckResource = ({ checked, forcedChecked }): void => {
    setDisplaySelectedOption({
      checked,
      forcedChecked
    });
    setAnchorEl(null);
    if (selectedResources.length <= 0) {
      return;
    }

    checkResource({
      data: {
        check: { is_forced: forcedChecked },
        resources: adjustCheckedResources({ resources: selectedResources })
      },
      endpoint: checkEndpoint
    }).then(() => {
      setSelectedResources([]);
      showSuccessMessage(
        checked ? t(labelCheckCommandSent) : t(labelForcedCheckCommandSent)
      );
    });
  };

  useEffect(() => {
    if (selectedResources.length <= 0 || !isNil(displaySelectedOption)) {
      return;
    }
    setDisplaySelectedOption({
      checked: canCheck(selectedResources),
      forcedChecked: canForcedCheck(selectedResources)
    });
  }, [selectedResources]);

  if (!disableCheck && displaySelectedOption?.checked) {
    return (
      <>
        <ResourceActionButton
          disabled={disableCheck}
          icon={<IconCheck />}
          label={t(labelCheck)}
          permitted={isCheckPermitted}
          secondaryIcon={<IconArrow />}
          testId={testId}
          onClick={displayPopover}
        />
        <ListCheckOptions
          isDefaultChecked
          anchorEl={anchorEl}
          disabled={{ disableCheck, disableForcedCheck }}
          open={Boolean(anchorEl)}
          onClickCheck={(): void =>
            handleCheckResource({ checked: true, forcedChecked: false })
          }
          onClickForcedCheck={(): void =>
            handleCheckResource({ checked: false, forcedChecked: true })
          }
          onClose={closePopover}
        />
      </>
    );
  }

  if (!disableForcedCheck && displaySelectedOption?.forcedChecked) {
    return (
      <>
        <ResourceActionButton
          disabled={disableForcedCheck}
          icon={<IconForcedCheck />}
          label={t(labelForcedCheck)}
          permitted={isForcedCheckPermitted}
          secondaryIcon={<IconArrow />}
          testId={testId}
          onClick={displayPopover}
        />
        <ListCheckOptions
          anchorEl={anchorEl}
          disabled={{ disableCheck, disableForcedCheck }}
          isDefaultChecked={false}
          open={Boolean(anchorEl)}
          onClickCheck={(): void =>
            handleCheckResource({ checked: true, forcedChecked: false })
          }
          onClickForcedCheck={(): void =>
            handleCheckResource({ checked: false, forcedChecked: true })
          }
          onClose={closePopover}
        />
      </>
    );
  }

  return (
    <ResourceActionButton
      disabled
      icon={
        displaySelectedOption?.forcedChecked ? (
          <IconForcedCheck />
        ) : (
          <IconCheck />
        )
      }
      label={
        displaySelectedOption?.forcedChecked
          ? t(labelForcedCheck)
          : t(labelCheck)
      }
      permitted={
        displaySelectedOption?.forcedChecked
          ? isForcedCheckPermitted
          : isCheckPermitted
      }
      secondaryIcon={<IconArrow />}
      testId={testId}
      onClick={(): void => undefined}
    />
  );
};

export default CheckActionButton;
