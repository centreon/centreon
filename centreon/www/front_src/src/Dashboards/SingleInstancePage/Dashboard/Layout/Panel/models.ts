import { SvgIconComponent } from '@mui/icons-material';

export interface ExpandableData {
  toggleExpand: () => void;
  Icon: SvgIconComponent;
  label: string;
  isExpanded: boolean;
}
