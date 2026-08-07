import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import type { SvgIconProps } from '@mui/material';

import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
  Icon: ComponentType<SvgIconProps>;
  actionLabel: string;
  description: string;
  href: string;
  tone?: 'navy' | 'primary';
  title: string;
}

const ResourceCard = ({
  Icon,
  title,
  description,
  actionLabel,
  href,
  tone = 'primary'
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <a
      className="flex items-start gap-1.5 rounded-lg border border-divider p-4 no-underline transition-[border-color,box-shadow] duration-150 hover:border-text-disabled hover:shadow-[0px_4px_4px_0px_rgba(0,0,0,0.15)]"
      href={href}
      rel="noreferrer noopener"
      target="_blank"
    >
      <div
        className={`flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded ${
          tone === 'navy' ? 'bg-[#131D5A]' : 'bg-primary-light'
        }`}
      >
        <Icon
          className={`text-[21px] ${tone === 'navy' ? 'text-white' : 'text-primary-main'}`}
        />
      </div>
      <div className="min-w-0">
        <p className="text-sm font-medium text-text-primary">{t(title)}</p>
        <p className="mt-0.5 mb-[3px] text-xs text-text-secondary">
          {t(description)}
        </p>
        <div className="flex items-center gap-1 font-medium text-primary-main">
          <span className="text-[13px]">{t(actionLabel)}</span>
          <ArrowForwardIcon className="text-[13px]" />
        </div>
      </div>
    </a>
  );
};

export default ResourceCard;
