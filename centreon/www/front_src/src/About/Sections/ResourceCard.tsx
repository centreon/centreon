import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import type { SvgIconProps } from '@mui/material';

import type { ComponentType, ReactElement } from 'react';
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
}: Props): ReactElement => {
  const { t } = useTranslation();

  return (
    <a
      className="flex items-start gap-2 rounded-lg border border-divider p-4 no-underline transition-[border-color,box-shadow] duration-150 hover:border-text-disabled hover:shadow-md"
      href={href}
      rel="noreferrer noopener"
      target="_blank"
    >
      <div
        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded ${
          tone === 'navy'
            ? 'bg-brand-navy'
            : 'bg-primary-light dark:bg-primary-dark'
        }`}
      >
        <Icon
          className={`text-xl ${
            tone === 'navy' ? 'text-white' : 'text-primary-main dark:text-white'
          }`}
        />
      </div>
      <div className="min-w-0">
        <p className="text-sm font-medium text-text-primary">{t(title)}</p>
        <p className="mt-1 mb-1 text-xs text-text-secondary">
          {t(description)}
        </p>
        <div className="flex items-center gap-1 font-medium text-primary-main">
          <span className="text-sm">{t(actionLabel)}</span>
          <ArrowForwardIcon className="text-sm" />
        </div>
      </div>
    </a>
  );
};

export default ResourceCard;
