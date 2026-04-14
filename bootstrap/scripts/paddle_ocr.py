#!/usr/bin/env python3
import sys
from paddleocr import PaddleOCR

def main():
    if len(sys.argv) < 2:
        raise SystemExit("Missing image path")

    image_path = sys.argv[1]

    ocr = PaddleOCR(
        lang='arabic',
        use_doc_orientation_classify=False,
        use_doc_unwarping=False,
        use_textline_orientation=False
    )

    result = ocr.predict(input=image_path)

    lines = []

    for page in result:
        page_res = page.res
        rec_texts = page_res.get('rec_texts', [])
        for text in rec_texts:
            if text:
                lines.append(str(text).strip())

    print("\n".join(lines))

if __name__ == "__main__":
    main()