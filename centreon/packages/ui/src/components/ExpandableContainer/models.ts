import { SvgIconComponent } from '@mui/icons-material';
import { CSSProperties, ForwardedRef } from 'react';

export interface Parameters {
  toggleExpand: () => void;
  Icon: SvgIconComponent;
  isExpanded: boolean;
  label: string;
  style?: CSSProperties;
  ref: ForwardedRef<HTMLDivElement>;
  key: string;
}
