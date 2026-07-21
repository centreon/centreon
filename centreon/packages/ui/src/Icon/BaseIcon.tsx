import { SvgIcon, type SvgIconProps, type SvgIconTypeMap } from '@mui/material';
import type { OverridableComponent } from '@mui/material/OverridableComponent';

import { has } from 'ramda';

interface Props extends SvgIconProps {
  Icon: JSX.Element | OverridableComponent<SvgIconTypeMap>;
  dataTestId?: string;
}

const BaseIcon = ({ Icon, dataTestId, ...props }: Props): JSX.Element => {
  if (!has('key', Icon)) {
    const Component = Icon as (props: SvgIconProps) => JSX.Element;

    return (
      <SvgIcon
        data-testid={dataTestId || (Icon as () => JSX.Element).name}
        {...props}
      >
        <Component />
      </SvgIcon>
    );
  }

  return (
    <SvgIcon data-testid={dataTestId} {...props}>
      {Icon as JSX.Element}
    </SvgIcon>
  );
};

export default BaseIcon;
