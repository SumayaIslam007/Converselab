import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import {createRoot, useState, useEffect} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { createNote, updateNote } from './api/notes';
import {useNotes} from './hooks/useNotes';
import { PanelBody, RangeControl, SelectControl, ToggleControl, Spinner, Placeholder } from '@wordpress/components';

apiFetch.use(apiFetch.createNonceMiddleware(converselabSettings.nonce));

const NoteForm = ({ onNoteSaved, editingNote, onCancelEdit }) => {
    const defaultState = { title: '', content: '', priority: 'medium', source_url: '' };
    const [formData, setFormData] = useState(defaultState);
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (editingNote) {
            setFormData({
                title: editingNote.title,
                content: editingNote.content || '',
                priority: editingNote.priority || 'medium',
                source_url: editingNote.source_url || ''
            });
        } else {
            setFormData(defaultState);
        }
    }, [editingNote]);

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});

        let validationErrors = {};
        if (!formData.title.trim()) validationErrors.title = 'Title is required.';
        if (!formData.content.trim()) validationErrors.content = 'Content is required.';

        if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            return;
        }

        setIsSubmitting(true);

        const apiCall = editingNote ? updateNote(editingNote.id, formData) : createNote(formData);

        apiCall
            .then(() => {
                setFormData(defaultState);
                setIsSubmitting(false);
                onNoteSaved();
                if (editingNote) onCancelEdit();
            })
            .catch((err) => {
                setErrors({ server: err.message || 'Server rejected the note.' });
                setIsSubmitting(false);
            });
    };

    return (
        <div className="card" style={{ 
            maxWidth: '800px', marginBottom: '20px', padding: '20px', marginTop: '20px', 
            borderLeft: editingNote ? '4px solid #2271b1' : 'none'
        }}>
            <h2 className="title" style={{ marginTop: 0 }}>
                {editingNote ? `Edit Note #${editingNote.id}` : 'Create New Note'}
            </h2>
            
            {errors.server && <div className="notice notice-error inline" style={{marginLeft: 0}}><p>{errors.server}</p></div>}
            
            <form onSubmit={handleSubmit}>
                <div style={{ marginBottom: '15px' }}>
                    <label><strong>Title <span style={{color: '#d63638'}}>*</span></strong></label><br/>
                    <input type="text" className="regular-text" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} />
                    {errors.title && <p style={{color: '#d63638', margin: '5px 0 0 0'}}>{errors.title}</p>}
                </div>

                <div style={{ marginBottom: '15px' }}>
                    <label><strong>Content <span style={{color: '#d63638'}}>*</span></strong></label><br/>
                    <textarea className="large-text" rows="4" value={formData.content} onChange={e => setFormData({...formData, content: e.target.value})}></textarea>
                    {errors.content && <p style={{color: '#d63638', margin: '5px 0 0 0'}}>{errors.content}</p>}
                </div>

                <div style={{ marginBottom: '15px' }}>
                    <label><strong>Priority</strong></label><br/>
                    <select value={formData.priority} onChange={e => setFormData({...formData, priority: e.target.value})}>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label><strong>Source URL</strong></label><br/>
                    <input type="url" className="regular-text" placeholder="https://" value={formData.source_url} onChange={e => setFormData({...formData, source_url: e.target.value})} />
                </div>

                <div style={{ display: 'flex', gap: '10px' }}>
                    <button type="submit" className="button button-primary" disabled={isSubmitting}>
                        {isSubmitting ? 'Saving...' : (editingNote ? 'Update Note' : 'Save Note')}
                    </button>
                    
                    {/* Only show the Cancel button if we are in Edit Mode */}
                    {editingNote && (
                        <button type="button" className="button button-secondary" onClick={onCancelEdit}>
                            Cancel Edit
                        </button>
                    )}
                </div>
            </form>
        </div>
    );
};

const NotesList = ({ notes, isLoading, error, onEditClick, onNoteDeleted }) => {
    if (isLoading) return <p>Loading your Converselab notes...</p>;
    if (error) return <div className="notice notice-error inline"><p><strong>Error:</strong> {error}</p></div>;
    if (notes.length === 0) return <div className="notice notice-info inline"><p>No notes found in the database. Time to create your first one!</p></div>;
    
    return (
        <table className="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th style={{ width: '50px' }}>ID</th>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Date Created</th>
                    <th style={{ width: '150px' }}>Actions</th>
                </tr>
            </thead>
            <tbody>
                {notes.map(note => (
                    <tr key={note.id}>
                        <td>{note.id}</td>
                        <td><strong>{note.title}</strong></td>
                        <td>
                            <span className={`converselab-badge priority-${note.priority}`}>
                                {note.priority ? note.priority.toUpperCase() : 'NORMAL'}
                            </span>
                        </td>
                        <td>{note.date}</td>
                        {/* NEW: The Edit and Delete Buttons */}
                        <td>
                            <button className="button button-small" onClick={() => onEditClick(note)} style={{ marginRight: '8px' }}>
                                Edit
                            </button>
                            <button className="button button-small button-link-delete" onClick={() => handleDelete(note.id)} style={{ color: '#d63638' }}>
                                Delete
                            </button>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
};

registerBlockType('converselab/notes-block', {
    title: 'Converslab Notes',
    icon: 'list-view',
    category: 'widgets',
    
    attributes: {
        count: { type: 'number', default: 3 },
        priority: { type: 'string', default: 'all' },
        showSource: { type: 'boolean', default: true }
    },
    
    edit: ({ attributes, setAttributes }) => {
        const { count, priority, showSource } = attributes;
        const [previewNotes, setPreviewNotes] = useState([]);
        const [isLoading, setIsLoading] = useState(true);

        useEffect(() => {
            setIsLoading(true);
            apiFetch({ url: converselabSettings.restUrl })
                .then(data => {
                    let filtered = data;
                    if (priority !== 'all') {
                        filtered = filtered.filter(n => n.priority === priority);
                    }
                    filtered.sort((a, b) => b.id - a.id);
                    setPreviewNotes(filtered.slice(0, count));
                    setIsLoading(false);
                })
                .catch(() => setIsLoading(false));
        }, [count, priority]);

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Display Settings" initialOpen={true}>
                        <RangeControl 
                            label="Number of Notes"
                            value={count} 
                            onChange={(val) => setAttributes({ count: val })}
                            min={1} max={10}
                        />
                        <SelectControl
                            label="Filter by Priority"
                            value={priority}
                            options={[
                                { label: 'Show All', value: 'all' },
                                { label: 'Low Only', value: 'low' },
                                { label: 'Medium Only', value: 'medium' },
                                { label: 'High Only', value: 'high' }
                            ]}
                            onChange={(val) => setAttributes({ priority: val })}
                        />
                        <ToggleControl
                            label="Show Source Link"
                            checked={showSource}
                            onChange={(val) => setAttributes({ showSource: val })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="converselab-block-preview" style={{ padding: '20px', border: '1px solid #e0e0e0', backgroundColor: '#fff' }}>
                    
                    {isLoading && (
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100px' }}>
                            <Spinner />
                        </div>
                    )}

                    {!isLoading && previewNotes.length === 0 && (
                        <Placeholder 
                            icon="list-view" 
                            label="Converslab Notes"
                            instructions="No notes found matching your criteria. Try changing the priority filter or create a new note in the dashboard."
                        />
                    )}

                    {!isLoading && previewNotes.length > 0 && (
                        <div>
                            <h3 style={{ marginTop: 0, fontSize: '18px', borderBottom: '2px solid #f0f0f0', paddingBottom: '10px' }}>
                                Latest Notes
                            </h3>
                            <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                                {previewNotes.map(note => (
                                    <li key={note.id} style={{ padding: '12px 0', borderBottom: '1px solid #eee' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <strong>{note.title}</strong>
                                            <span className={`converselab-badge priority-${note.priority}`} 
                                                style={{ fontSize: '10px', padding: '2px 6px', borderRadius: '4px', background: '#f0f0f0', color: '#555' }}>
                                                {note.priority.toUpperCase()}
                                            </span>
                                        </div>
                                        
                                        {showSource && note.source_url && (
                                            <div style={{ fontSize: '12px', marginTop: '4px', color: '#0073aa' }}>
                                                🔗 {note.source_url}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </>
        );
    },
    
    save: () => {
        return null; 
    }
});

const App = () => {
    // Uses our new custom hook!
    const { notes, isLoading, error, refreshNotes, removeNote } = useNotes();
    const [editingNote, setEditingNote] = useState(null);

    return (
        <div className="wrap">
            <h1 className="wp-heading-inline">Converselab Notes Database</h1>
            <hr className="wp-header-end" />
            
            <NoteForm 
                onNoteSaved={refreshNotes} 
                editingNote={editingNote} 
                onCancelEdit={() => setEditingNote(null)} 
            />
            
            <NotesList
                notes={notes} 
                isLoading={isLoading}
                error={error}
                onNoteDeleted={removeNote}
                onEditClick={(note) => {
                    setEditingNote(note);
                    window.scrollTo(0, 0); // Smoothly scrolls back up to the form!
                }}
            />
        </div>
    );
};

const rootElement=document.getElementById('converselab-admin-app');

if(rootElement){
    const root=createRoot(rootElement);
    root.render(<App />);
}