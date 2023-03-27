import { AutoLinkPlugin } from '@lexical/react/LexicalAutoLinkPlugin';

interface LinkAttributes {
  rel?: null | string;
  target?: null | string;
}

interface LinkMatcherResult {
  attributes?: LinkAttributes;
  index: number;
  length: number;
  text: string;
  url: string;
}

const URL_MATCHER =
  /((https?:\/\/(www\.)?)|(www\.))[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)/;

const EMAIL_MATCHER =
  /(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))/;

const MATCHERS = [
  (text: string): LinkMatcherResult | null => {
    const match = URL_MATCHER.exec(text);

    return (
      match && {
        attributes: { rel: 'noreferrer', target: '_blank' },
        index: match.index,
        length: match[0].length,
        text: match[0],
        url: match[0]
      }
    );
  },
  (text: string): LinkMatcherResult | null => {
    const match = EMAIL_MATCHER.exec(text);

    return (
      match && {
        index: match.index,
        length: match[0].length,
        text: match[0],
        url: `mailto:${match[0]}`
      }
    );
  }
];

const AutoCompleteLinkPlugin = (): JSX.Element => {
  return <AutoLinkPlugin matchers={MATCHERS} />;
};

export default AutoCompleteLinkPlugin;
