import ZoomPreview from './ZoomPreview';
import { ZoomPreviewData } from './ZoomPreview/models';

interface Props {
  renderAreaToInteractWith: JSX.Element;
  zoomPreviewData: ZoomPreviewData;
}

const InteractionWithGraph = ({
  zoomPreviewData,
  renderAreaToInteractWith
}: Props): JSX.Element => {
  return (
    <g>
      <ZoomPreview {...zoomPreviewData} />
      {renderAreaToInteractWith}
    </g>
  );
};

export default InteractionWithGraph;
