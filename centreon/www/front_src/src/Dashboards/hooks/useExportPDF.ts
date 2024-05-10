import JsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import dayjs from 'dayjs';
import { isEmpty } from 'ramda';

import CentreonLogo from '../../../../img/logo-centreon.jpg';

interface Options {
  filename?: string;
}

interface UseExportPDFProps {
  description: string;
  exportOptions?: Options;
  name: string;
  targetElm;
  widgetsCoordinates: Array<number>;
}

interface UseExportPDFState {
  exportPDf: () => void;
}

const useExportPDF = ({
  targetElm,
  name,
  description,
  widgetsCoordinates
}: UseExportPDFProps): UseExportPDFState => {
  const currentDate = dayjs().format('YYYY-MM-DD');
  const currentHour = dayjs().format('HH:mm:ss');

  const addHTMLTitle = async (pdfObject, pdfWidth) => {
    pdfObject.addImage(CentreonLogo, 'JPG', 10, 5);

    const nameY = isEmpty(description) ? 32 : 30;

    pdfObject.setFont('helvetica', 'bold');
    pdfObject.setFontSize(18);
    pdfObject.text(name, 10, nameY);

    pdfObject.setFontSize(9);
    pdfObject.text(currentDate, 168, 32);

    pdfObject.setFont('helvetica', 'normal');
    pdfObject.setFontSize(9);
    pdfObject.text(currentHour, 186, 32);

    pdfObject.setFontSize(8);
    pdfObject.text(description, 10, 35);

    pdfObject.setDrawColor(63, 133, 213);
    pdfObject.setLineWidth(0.2);
    pdfObject.line(10, 37, pdfWidth - 10, 37);
  };

  const exportPDf = async () => {
    if (!targetElm) {
      console.error('Element not found');

      return;
    }

    try {
      const originalCanvas = await html2canvas(targetElm, {
        scale: 2,
        useCORS: true
      });

      const pdf = new JsPDF('p', 'mm', 'a4');
      const pageHeight = 297;
      const pageWidth = 210;
      const inlinePadding = 10;
      const topPadding = 48;
      const imgWidth = pageWidth - inlinePadding * 2;

      addHTMLTitle(pdf, pageWidth);

      const cropAndAddToPDF = (cropStartY, cropHeight, position) => {
        const croppedCanvas = document.createElement('canvas');
        croppedCanvas.width = originalCanvas.width;
        croppedCanvas.height = cropHeight;
        const ctx = croppedCanvas.getContext('2d');

        ctx.drawImage(
          originalCanvas,
          0,
          cropStartY,
          originalCanvas.width,
          cropHeight,
          0,
          0,
          croppedCanvas.width,
          croppedCanvas.height
        );

        const contentDataURL = croppedCanvas.toDataURL('image/jpeg', 1.0);
        const displayHeight =
          (croppedCanvas.height * imgWidth) / croppedCanvas.width;

        pdf.addImage(
          contentDataURL,
          'JPEG',
          inlinePadding,
          position,
          imgWidth,
          displayHeight
        );
      };

      const usableWidth = pageWidth - inlinePadding;
      const usableHeight = pageHeight - topPadding;
      const canvasWidth = originalCanvas.width;
      const canvasHeight = originalCanvas.height;

      const pageFactor = usableHeight / usableWidth;
      const canvaFacor = canvasHeight / canvasWidth;

      const fitsfactor = canvaFacor / pageFactor;
      const imageFits = fitsfactor > 1;

      const topCropHeightPercent = fitsfactor < 1 ? 1 : 1 / fitsfactor;

      const closestInferiorwidgetY = Math.max(
        ...widgetsCoordinates.filter((y) => y < topCropHeightPercent)
      );

      const topCropHeight =
        originalCanvas.height *
        (imageFits ? closestInferiorwidgetY : topCropHeightPercent);

      cropAndAddToPDF(0, topCropHeight, topPadding);

      if (imageFits) {
        pdf.addPage();

        const bottomCropStartY = topCropHeight;
        const bottomCropHeight = originalCanvas.height - topCropHeight;
        cropAndAddToPDF(bottomCropStartY, bottomCropHeight, 10);
      }

      pdf.save(`${name}.pdf`);
    } catch (error) {
      console.error('Error generating PDF:', error);
    }
  };

  return { exportPDf };
};

export default useExportPDF;
