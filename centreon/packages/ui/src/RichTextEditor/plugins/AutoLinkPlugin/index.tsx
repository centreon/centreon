import { useEffect, useState } from 'react';

import {
  AutoLinkPlugin,
  LinkMatcher
} from '@lexical/react/LexicalAutoLinkPlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { BLUR_COMMAND, COMMAND_PRIORITY_LOW, FOCUS_COMMAND } from 'lexical';

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

const urlMatcher =
  /((https?:\/\/(www\.)?)|(www\.))[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)/;

const emailMatcher =
  /(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))/;

const getMatchers = (openLinkInNewTab: boolean): Array<LinkMatcher> => [
  (text: string): LinkMatcherResult | null => {
    const match = urlMatcher.exec(text);

    return (
      match && {
        attributes: openLinkInNewTab
          ? { rel: 'noreferrer', target: '_blank' }
          : undefined,
        index: match.index,
        length: match[0].length,
        text: match[0],
        url: match[0]
      }
    );
  },
  (text: string): LinkMatcherResult | null => {
    const match = emailMatcher.exec(text);

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

const AutoCompleteLinkPlugin = ({
  openLinkInNewTab
}: {
  openLinkInNewTab: boolean;
}): JSX.Element | null => {
  const [editor] = useLexicalComposerContext();
  const [hasFocus, setFocus] = useState(false);

  useEffect(
    () =>
      editor.registerCommand(
        BLUR_COMMAND,
        () => {
          setFocus(false);

          return false;
        },
        COMMAND_PRIORITY_LOW
      ),
    []
  );

  useEffect(
    () =>
      editor.registerCommand(
        FOCUS_COMMAND,
        () => {
          setFocus(true);

          return false;
        },
        COMMAND_PRIORITY_LOW
      ),
    []
  );

  if (!hasFocus) {
    return null;
  }

  return <AutoLinkPlugin matchers={getMatchers(openLinkInNewTab)} />;
};

export default AutoCompleteLinkPlugin;
