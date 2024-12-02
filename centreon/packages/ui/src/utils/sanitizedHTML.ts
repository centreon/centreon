import ReactHtmlParser from 'html-react-parser';
import sanitizeHtml, { type IOptions } from 'sanitize-html';

interface UseSanitizedHTMLProps {
  initialContent: string;
  sanitizeOptions?: IOptions;
}

const sanitizedHTML = ({
  initialContent,
  sanitizeOptions = {}
}: UseSanitizedHTMLProps): JSX.Element => {
  const sanitizedContent = sanitizeHtml(initialContent, sanitizeOptions);

  return ReactHtmlParser(sanitizedContent);
};

export { sanitizedHTML };
