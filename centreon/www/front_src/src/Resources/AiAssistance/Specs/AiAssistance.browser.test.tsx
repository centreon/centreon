import AnalyzeResources from './AiAssistance.AnalyzeResources';
import AskAiFilter from './AiAssistance.AskAiFilter';
import ExplainGraph from './AiAssistance.ExplainGraph';
import MarkdownContent from './AiAssistance.MarkdownContent';
import SummarizeIncident from './AiAssistance.SummarizeIncident';

describe('AI assistance: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  AskAiFilter();
  SummarizeIncident();
  AnalyzeResources();
  ExplainGraph();
  MarkdownContent();
});
