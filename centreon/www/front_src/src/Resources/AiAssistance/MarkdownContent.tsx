import { Typography } from '@mui/material';

import Markdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

// The rendered elements carry no class of their own: they are styled from the
// container, through arbitrary variants.
const contentClassName = [
  'text-text-primary',
  '*:first:mt-0',
  '*:last:mb-0',
  '[&_a]:text-primary-main',
  '[&_code]:rounded [&_code]:bg-action-hover [&_code]:px-1',
  // relative to the surrounding text, which has no token
  '[&_code]:text-[0.85em]',
  '[&_li]:mb-0.5',
  '[&_p]:my-1',
  '[&_table]:border-collapse',
  '[&_:is(td,th)]:border [&_:is(td,th)]:border-divider',
  '[&_:is(td,th)]:px-2 [&_:is(td,th)]:py-0.5',
  '[&_:is(ul,ol)]:my-1 [&_:is(ul,ol)]:pl-5'
].join(' ');

// Headings keep the theme's subtitle2 typography, in bold.
const Heading = ({
  node,
  ...props
}: JSX.IntrinsicElements['h1'] & {
  node?: { tagName: string };
}): JSX.Element => (
  <Typography
    {...props}
    className="mt-3 mb-1 font-bold"
    component={(node?.tagName ?? 'h6') as 'h1'}
    variant="subtitle2"
  />
);

// An answer may echo text from monitored data: an image would be loaded
// without any click, sending its URL to a third party. Only its text is shown.
const ImageAsText = ({ alt }: JSX.IntrinsicElements['img']): JSX.Element => (
  <span>{alt}</span>
);

const Link = ({
  node,
  ...props
}: JSX.IntrinsicElements['a'] & { node?: unknown }): JSX.Element => (
  <a {...props} rel="noopener noreferrer" target="_blank" />
);

const components: Components = {
  a: Link,
  h1: Heading,
  h2: Heading,
  h3: Heading,
  h4: Heading,
  h5: Heading,
  h6: Heading,
  img: ImageAsText
};

interface Props {
  content: string;
}

export const MarkdownContent = ({ content }: Props): JSX.Element => (
  // the theme's body2 typography: no Tailwind token matches it
  <Typography className={contentClassName} component="div" variant="body2">
    <Markdown components={components} remarkPlugins={[remarkGfm]}>
      {content}
    </Markdown>
  </Typography>
);
