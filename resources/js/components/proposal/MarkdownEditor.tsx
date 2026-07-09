import MDEditor from '@uiw/react-md-editor';
import '@uiw/react-md-editor/markdown-editor.css';
import '@uiw/react-markdown-preview/markdown.css';

/**
 * Thin default-export wrapper around @uiw/react-md-editor so the Proposal
 * builder can React.lazy() it — app.tsx globs pages eagerly, and the editor
 * (+ its CSS) is far too heavy to ship in the main bundle every BO downloads.
 */
export default function MarkdownEditor({ value, onChange }: { value: string; onChange: (value: string) => void }) {
    return (
        <div data-color-mode="dark">
            <MDEditor value={value} onChange={(next) => onChange(next ?? '')} height={480} preview="edit" />
        </div>
    );
}
