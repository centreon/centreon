import sanitizeHtml, { IOptions } from 'sanitize-html';
import ReactHtmlParser from 'react-html-parser';

interface UseSanitizedHTMLProps {
  initialContent: string;
  sanitizeOptions?: IOptions;
}

const useSanitizedHTML = ({
  initialContent,
  sanitizeOptions = {}
}: UseSanitizedHTMLProps): JSX.Element => {
  const sanitizedContent = sanitizeHtml(initialContent, sanitizeOptions);

  return ReactHtmlParser(sanitizedContent);
};

export default useSanitizedHTML;
