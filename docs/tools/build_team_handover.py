"""Export TEAM-HANDOVER.md to a styled, dependency-free Word document.

Run from any directory: python docs/tools/build_team_handover.py
Only the Python standard library is required. The exporter supports the Markdown
subset used by the handover: headings, paragraphs, tables, lists, links and code.
"""

from __future__ import annotations

import hashlib
import re
import xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import quote
from xml.sax.saxutils import escape
from zipfile import ZIP_DEFLATED, ZipFile


DOCS = Path(__file__).resolve().parents[1]
SOURCE = DOCS / "TEAM-HANDOVER.md"
OUTPUT = DOCS / "E-KHMER-Team-Handover.docx"
W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
REL = "http://schemas.openxmlformats.org/package/2006/relationships"
NS = f'xmlns:w="{W}" xmlns:r="{R}"'


def xml(value: str) -> str:
    return escape(value, {'"': "&quot;"})


class WordExport:
    def __init__(self, source: str):
        self.source = source
        self.links: dict[str, str] = {}
        self.headings = re.findall(r"^## (.+)$", source, re.MULTILINE)
        self.numberings: list[int] = []
        self.table_count = 0
        self.code_count = 0

    def run(self, text: str, props: str = "") -> str:
        return f'<w:r><w:rPr>{props}</w:rPr><w:t xml:space="preserve">{xml(text)}</w:t></w:r>'

    def inline(self, text: str, props: str = "") -> str:
        parts = []
        tokens = re.split(r"(\[[^\]]+\]\([^)]+\)|\*\*[^*]+\*\*|`[^`]+`)", text)
        for token in tokens:
            if not token:
                continue
            link = re.fullmatch(r"\[([^\]]+)\]\(([^)]+)\)", token)
            if link:
                label, target = link.groups()
                if target not in self.links:
                    self.links[target] = f"rId{100 + len(self.links)}"
                parts.append(f'<w:hyperlink r:id="{self.links[target]}">' + self.run(label, props + '<w:rStyle w:val="Hyperlink"/>') + '</w:hyperlink>')
            elif token.startswith("**"):
                parts.append(self.run(token[2:-2], props + "<w:b/>"))
            elif token.startswith("`"):
                parts.append(self.run(token[1:-1], props + '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="19"/><w:color w:val="155E75"/>'))
            else:
                parts.append(self.run(token, props))
        return "".join(parts)

    def paragraph(self, text: str = "", style: str = "Normal", extra: str = "", props: str = "") -> str:
        return f'<w:p><w:pPr><w:pStyle w:val="{style}"/>{extra}</w:pPr>{self.inline(text, props)}</w:p>'

    def table(self, lines: list[str]) -> str:
        self.table_count += 1
        rows = [[cell.strip() for cell in line.strip().strip("|").split("|")] for line in lines]
        rows = [row for row in rows if not all(re.fullmatch(r":?-{3,}:?", cell) for cell in row)]
        count = len(rows[0])
        if any(len(row) != count for row in rows):
            raise ValueError("Inconsistent table columns: " + repr(rows))
        widths = {2: [3100, 6670], 3: [2300, 3050, 4420]}.get(count, [9770 // count] * count)
        grid = "".join(f'<w:gridCol w:w="{width}"/>' for width in widths)
        body = []
        for row_index, row in enumerate(rows):
            header = row_index == 0
            cells = []
            for index, value in enumerate(row):
                fill = "16324F" if header else ("F0F5F9" if row_index % 2 else "FFFFFF")
                props = '<w:sz w:val="18"/>' + ('<w:b/><w:color w:val="FFFFFF"/>' if header else "")
                # Make inline code in the dark header readable as well.
                value = value.replace("`", "") if header else value
                cell = f'<w:tc><w:tcPr><w:tcW w:w="{widths[index]}" w:type="dxa"/><w:shd w:fill="{fill}"/><w:vAlign w:val="top"/></w:tcPr>'
                cell += self.paragraph(value, "TableText", props=props) + '</w:tc>'
                cells.append(cell)
            row_props = '<w:tblHeader/>' if header else ""
            body.append('<w:tr><w:trPr><w:cantSplit/>' + row_props + '</w:trPr>' + "".join(cells) + '</w:tr>')
        return '<w:tbl><w:tblPr><w:tblW w:w="9770" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="90" w:type="dxa"/><w:left w:w="105" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="105" w:type="dxa"/></w:tblCellMar><w:tblBorders><w:insideH w:val="single" w:sz="4" w:color="DCE5ED"/></w:tblBorders></w:tblPr><w:tblGrid>' + grid + '</w:tblGrid>' + "".join(body) + '</w:tbl>' + self.paragraph("", extra='<w:spacing w:after="60"/>')

    def document(self) -> str:
        before, content = self.source.split("\n## ", 1)
        content = "## " + content
        preamble = [block for block in before.strip().split("\n\n") if block.strip()]
        body = [self.paragraph("E-KHMER", "CoverEyebrow"), self.paragraph("TEAM HANDOVER", "Title"), self.paragraph("Complete project walkthrough, implementation record and operating guide", "Subtitle")]
        for block in preamble[2:]:
            body.append(self.paragraph(block.replace("\n", " "), "CoverMeta" if block.startswith("**Prepared:") else "Normal"))
        body.append(self.paragraph("Source-based edition • 19 October 2026", "CoverMeta"))
        body.append(self.paragraph("Contents", "ContentsTitle", '<w:pageBreakBefore/>'))
        body.append(self.paragraph("Select a chapter to jump to it. The Navigation Pane also lists the document headings.", "Small"))
        for number, title in enumerate(self.headings, 1):
            body.append('<w:p><w:pPr><w:pStyle w:val="ContentsEntry"/></w:pPr>' + f'<w:hyperlink w:anchor="chapter_{number}">' + self.run(title, '<w:color w:val="16324F"/>') + '</w:hyperlink></w:p>')
        body.append(self.paragraph("Repository baseline: f950a779c0517472ac62321beda890db0639dfc5. Referenced file links work when this document stays in the repository docs folder.", "Small"))
        lines = content.splitlines()
        index = 0
        chapter = 0
        numbered = False
        list_id = 0
        while index < len(lines):
            line = lines[index]
            if not line.strip():
                numbered = False
                index += 1
                continue
            if line.startswith("```"):
                code = []
                index += 1
                while index < len(lines) and not lines[index].startswith("```"):
                    code.append(lines[index])
                    index += 1
                if index == len(lines):
                    raise ValueError("Unclosed code fence")
                self.code_count += 1
                for code_index, code_line in enumerate(code):
                    keep = '<w:keepNext/>' if code_index < len(code) - 1 else ""
                    body.append('<w:p><w:pPr><w:pStyle w:val="CodeBlock"/>' + keep + '</w:pPr>' + self.run(code_line, '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>') + '</w:p>')
                body.append(self.paragraph("", extra='<w:spacing w:after="50"/>'))
            elif line.startswith("|"):
                table_lines = []
                while index < len(lines) and lines[index].startswith("|"):
                    table_lines.append(lines[index])
                    index += 1
                body.append(self.table(table_lines))
                continue
            elif line.startswith("## "):
                chapter += 1
                body.append('<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr>' + f'<w:bookmarkStart w:id="{chapter}" w:name="chapter_{chapter}"/>' + self.inline(line[3:]) + f'<w:bookmarkEnd w:id="{chapter}"/></w:p>')
            elif line.startswith("### "):
                body.append(self.paragraph(line[4:], "Heading2"))
            elif line.startswith("- "):
                body.append(self.paragraph(line[2:], "ListParagraph", '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'))
            elif re.match(r"^\d+\. ", line):
                if not numbered:
                    list_id = 2 + len(self.numberings)
                    self.numberings.append(list_id)
                numbered = True
                body.append(self.paragraph(re.sub(r"^\d+\. ", "", line), "ListParagraph", f'<w:numPr><w:ilvl w:val="0"/><w:numId w:val="{list_id}"/></w:numPr>'))
            else:
                paragraph_lines = [line]
                while index + 1 < len(lines) and lines[index + 1].strip() and not re.match(r"^(#{1,3} |\||```|- |\d+\. )", lines[index + 1]):
                    index += 1
                    paragraph_lines.append(lines[index])
                body.append(self.paragraph(" ".join(paragraph_lines)))
            index += 1
        section = '<w:sectPr><w:headerReference w:type="default" r:id="rId3"/><w:footerReference w:type="default" r:id="rId4"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1080" w:right="1068" w:bottom="1080" w:left="1068" w:header="500" w:footer="500"/><w:titlePg/></w:sectPr>'
        return f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document {NS}><w:body>' + "".join(body) + section + '</w:body></w:document>'

    def numbering(self) -> str:
        abstract = '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="singleLevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="320"/></w:tabs><w:ind w:left="320" w:hanging="220"/></w:pPr></w:lvl></w:abstractNum>'
        abstract += '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="singleLevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="360" w:hanging="300"/></w:pPr></w:lvl></w:abstractNum>'
        nums = '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
        nums += "".join(f'<w:num w:numId="{number}"><w:abstractNumId w:val="1"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>' for number in self.numberings)
        return f'<w:numbering xmlns:w="{W}">' + abstract + nums + '</w:numbering>'

    def relationships(self) -> str:
        entries = [('rId1', 'styles', 'styles.xml'), ('rId2', 'numbering', 'numbering.xml'), ('rId3', 'header', 'header1.xml'), ('rId4', 'footer', 'footer1.xml'), ('rId5', 'settings', 'settings.xml')]
        result = [f'<Relationship Id="{rid}" Type="{R}/{kind}" Target="{target}"/>' for rid, kind, target in entries]
        for target, rid in self.links.items():
            encoded = quote(target, safe="/:#?=&%")
            result.append(f'<Relationship Id="{rid}" Type="{R}/hyperlink" Target="{xml(encoded)}" TargetMode="External"/>')
        return f'<Relationships xmlns="{REL}">' + "".join(result) + '</Relationships>'


def styles() -> str:
    definitions = []

    def style(name, size=22, color="25364A", p="", r="", base="Normal", kind="paragraph"):
        definitions.append(f'<w:style w:type="{kind}" w:styleId="{name}"><w:name w:val="{name}"/>' + (f'<w:basedOn w:val="{base}"/>' if name != "Normal" else "") + f'<w:pPr>{p}</w:pPr><w:rPr><w:color w:val="{color}"/><w:sz w:val="{size}"/>{r}</w:rPr></w:style>')

    style("Normal", p='<w:spacing w:after="135" w:line="280" w:lineRule="auto"/><w:widowControl/>')
    style("Title", 64, "16324F", '<w:spacing w:before="200" w:after="300"/>', '<w:b/>')
    style("Subtitle", 30, "155E75", '<w:spacing w:after="600"/>')
    style("CoverEyebrow", 28, "155E75", '<w:spacing w:before="1100" w:after="150"/>', '<w:b/><w:spacing w:val="70"/>')
    style("CoverMeta", 21, "52677C", '<w:spacing w:before="200" w:after="260"/>')
    style("Heading1", 36, "16324F", '<w:pageBreakBefore/><w:keepNext/><w:keepLines/><w:outlineLvl w:val="0"/><w:spacing w:before="120" w:after="260"/>', '<w:b/>')
    style("Heading2", 26, "155E75", '<w:keepNext/><w:keepLines/><w:outlineLvl w:val="1"/><w:spacing w:before="280" w:after="120"/>', '<w:b/>')
    style("ContentsTitle", 36, "16324F", '<w:keepNext/><w:spacing w:after="260"/>', '<w:b/>')
    style("ContentsEntry", 22, "16324F", '<w:spacing w:after="120"/>')
    style("Small", 18, "52677C", '<w:spacing w:after="150"/>')
    style("TableText", 18, p='<w:spacing w:after="30" w:line="230" w:lineRule="auto"/>')
    style("CodeBlock", 17, "25364A", '<w:spacing w:after="0" w:line="210" w:lineRule="auto"/><w:ind w:left="150" w:right="150"/><w:shd w:fill="F0F5F9"/>', '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>')
    style("ListParagraph", p='<w:spacing w:after="100"/>')
    style("Hyperlink", color="087A91", r='<w:u w:val="single"/>', kind="character", base="DefaultParagraphFont")
    return f'<w:styles xmlns:w="{W}"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:lang w:val="en-US"/></w:rPr></w:rPrDefault></w:docDefaults>' + "".join(definitions) + '</w:styles>'


def validate(parts: dict[str, str], source: str) -> None:
    for path, content in parts.items():
        if path.endswith((".xml", ".rels")):
            ET.fromstring(content)
    for target in re.findall(r"\]\(([^)]+)\)", source):
        if "://" not in target and not (DOCS / target).resolve().exists():
            raise ValueError("Missing source link: " + target)
    doc = ET.fromstring(parts["word/document.xml"])
    texts = " ".join(node.text or "" for node in doc.iter(f"{{{W}}}t"))
    for marker in ("385 passed", "2,615 assertions", "72 desktop/Pixel", "37 business tables", "17. Known issues"):
        if marker not in texts:
            raise ValueError("Missing expected document content: " + marker)
    for node in doc.iter(f"{{{W}}}tc"):
        if len(node) == 0 or node[-1].tag != f"{{{W}}}p":
            raise ValueError("A Word table cell must end in a paragraph")


def main() -> None:
    source = SOURCE.read_text(encoding="utf-8-sig")
    export = WordExport(source)
    document = export.document()
    digest = hashlib.sha256(SOURCE.read_bytes()).hexdigest()
    types = [('document', 'document.main'), ('styles', 'styles'), ('numbering', 'numbering'), ('header1', 'header'), ('footer1', 'footer'), ('settings', 'settings')]
    overrides = "".join(f'<Override PartName="/word/{name}.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.{mime}+xml"/>' for name, mime in types)
    parts = {
        '[Content_Types].xml': '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' + overrides + '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
        '_rels/.rels': f'<Relationships xmlns="{REL}"><Relationship Id="rId1" Type="{R}/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
        'word/document.xml': document,
        'word/styles.xml': styles(),
        'word/numbering.xml': export.numbering(),
        'word/_rels/document.xml.rels': export.relationships(),
        'word/settings.xml': f'<w:settings xmlns:w="{W}"><w:updateFields w:val="true"/><w:defaultTabStop w:val="720"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>',
        'word/header1.xml': f'<w:hdr {NS}>' + export.paragraph('E-KHMER  |  TEAM HANDOVER', 'Small', '<w:pBdr><w:bottom w:val="single" w:sz="5" w:color="CBD8E5"/></w:pBdr>') + '</w:hdr>',
        'word/footer1.xml': f'<w:ftr {NS}><w:p><w:pPr><w:jc w:val="right"/></w:pPr>' + export.run('19 October 2026  •  ', '<w:sz w:val="17"/><w:color w:val="52677C"/>') + '<w:fldSimple w:instr="PAGE"><w:r><w:t>1</w:t></w:r></w:fldSimple></w:p></w:ftr>',
        'docProps/core.xml': '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>E-KHMER Team Handover</dc:title><dc:subject>Project implementation, onboarding and operations</dc:subject><dc:creator>E-KHMER Project Team</dc:creator><dc:description>' + xml('Source: docs/TEAM-HANDOVER.md; SHA-256: ' + digest + '; baseline: f950a779c0517472ac62321beda890db0639dfc5') + '</dc:description><dcterms:created xsi:type="dcterms:W3CDTF">2026-10-19T00:00:00Z</dcterms:created></cp:coreProperties>',
    }
    validate(parts, source)
    with ZipFile(OUTPUT, 'w', ZIP_DEFLATED) as archive:
        for name, data in parts.items():
            archive.writestr(name, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' + data if not data.startswith('<?xml') else data)
    with ZipFile(OUTPUT) as archive:
        if archive.testzip() is not None:
            raise ValueError('Word ZIP integrity check failed')
    print(f'Created {OUTPUT}')
    print(f'{len(export.headings)} chapters, {export.table_count} tables, {export.code_count} code blocks; {len(source.split()):,} source words.')
    print('All package XML, expected content, table cells, source links and ZIP integrity checks passed.')


if __name__ == '__main__':
    main()
