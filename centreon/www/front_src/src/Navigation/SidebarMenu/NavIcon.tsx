import { isNil } from 'ramda';

import { Page } from '../models';
import administrationIcon from './icons/nav-administration.svg';
import collapseIcon from './icons/nav-collapse.svg';
import configurationIcon from './icons/nav-configuration.svg';
import dashboardIcon from './icons/nav-dashboard.svg';
import expandIcon from './icons/nav-expand.svg';
import reportingIcon from './icons/nav-reporting.svg';
import resourcesIcon from './icons/nav-resources.svg';

export const navIcons = {
  collapse: collapseIcon as string,
  expand: expandIcon as string
};

const sectionIcons: Record<string, string> = {
  administration: administrationIcon,
  configuration: configurationIcon,
  home: dashboardIcon,
  monitoring: resourcesIcon,
  reporting: reportingIcon
};

export const getSectionIcon = (page: Page): string | undefined =>
  isNil(page.icon) ? undefined : sectionIcons[page.icon];

interface NavIconProps {
  className?: string;
  colorClassName?: string;
  src?: string;
}

// SVGs are rendered as CSS masks so they follow the current text color.
export const NavIcon = ({
  src,
  className = 'size-6',
  colorClassName = 'bg-current'
}: NavIconProps): JSX.Element => (
  <span
    aria-hidden
    className={`inline-block shrink-0 ${colorClassName} ${className}`}
    style={
      src
        ? {
            maskImage: `url("${src}")`,
            maskPosition: 'center',
            maskRepeat: 'no-repeat',
            maskSize: 'contain',
            WebkitMaskImage: `url("${src}")`,
            WebkitMaskPosition: 'center',
            WebkitMaskRepeat: 'no-repeat',
            WebkitMaskSize: 'contain'
          }
        : undefined
    }
  />
);

interface CaretProps {
  className: string;
  isOpen: boolean;
}

export const Caret = ({ isOpen, className }: CaretProps): JSX.Element => (
  <svg
    aria-hidden
    className={`shrink-0 transition-transform duration-150 ${isOpen ? 'rotate-180' : ''} ${className}`}
    fill="none"
    stroke="currentColor"
    strokeLinecap="round"
    strokeLinejoin="round"
    strokeWidth="2"
    viewBox="0 0 24 24"
  >
    <polyline points="6 9 12 15 18 9" />
  </svg>
);
