import { Image, ImageVariant } from '@centreon/ui';

import emailIcon from './Icons/email.svg';
import slackIcon from './Icons/slack.svg';
import smsIcon from './Icons/sms.svg';

export const SlackIcon = (): JSX.Element => {
  return (
    <Image alt="slack" imagePath={slackIcon} variant={ImageVariant.Contain} />
  );
};

export const EmailIcon = (): JSX.Element => {
  return (
    <Image alt="email" imagePath={emailIcon} variant={ImageVariant.Contain} />
  );
};

export const SmsIcon = (): JSX.Element => {
  return <Image alt="sms" imagePath={smsIcon} variant={ImageVariant.Contain} />;
};
