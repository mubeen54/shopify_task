import {
    Page,
    Layout,
    TextField,
    Button,
    Card,
    Toast,
    Frame,
    Select,
    DropZone,
    Thumbnail,
    Grid,
} from '@shopify/polaris';
import { useState, useEffect, useRef } from 'react';
import useAxios from '../hooks/useAxios';
import { useNavigate } from 'react-router-dom'; // Import useNavigate
import 'summernote/dist/summernote-lite.css';
import $ from 'jquery';
import 'summernote/dist/summernote-lite.js';

const ProductForm = () => {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [price, setPrice] = useState('');
    const [category, setCategory] = useState('');
    const [imageUrl, setImageUrl] = useState('');
    const [fileUpload, setFileUpload] = useState(null);
    const [loading, setLoading] = useState(false);
    const [toastActive, setToastActive] = useState(false);
    const { axios } = useAxios();
    const summernoteRef = useRef(null);
    const navigate = useNavigate(); // Initialize the navigation function

    useEffect(() => {
        $(summernoteRef.current).summernote({
            placeholder: 'Enter product description...',
            tabsize: 2,
            height: 200,
            callbacks: {
                onChange: function(contents) {
                    setDescription(contents);
                }
            }
        });

        // Cleanup on unmount
        return () => {
            $(summernoteRef.current).summernote('destroy');
        };
    }, []);

    const toggleToastActive = () => setToastActive((active) => !active);

    const categoryOptions = [
        { label: 'Select category', value: '' },
        { label: 'Electronics', value: 'electronics' },
        { label: 'Clothing', value: 'clothing' },
        { label: 'Books', value: 'books' },
        { label: 'Home', value: 'home' },
        { label: 'Beauty', value: 'beauty' },
    ];

    const handleImageUpload = async (file) => {
        if (!file) {
            alert('Please select an image file.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        setLoading(true);
        try {
            const response = await axios.post('/hello/upload-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setImageUrl(response.data.imageUrl);
        } catch (error) {
            console.error('Failed to upload image:', error.response?.data || error.message);
            alert('Failed to upload image');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async () => {
        if (!imageUrl) {
            alert('Please upload an image before submitting.');
            return;
        }

        setLoading(true);
        try {
            const payload = { title, description, price, category, image: imageUrl };
            await axios.post('/product/store', payload);
            setTitle('');
            setDescription('');
            setPrice('');
            setCategory('');
            setImageUrl('');
            setFileUpload(null);
            setToastActive(true);

            $(summernoteRef.current).summernote('reset'); // Clear the editor

            // Redirect to the ProductCreator page after successful submission
            setTimeout(() => navigate('/'), 2000); // Redirect after 2 seconds (to let the toast show)
        } catch (error) {
            console.error('Failed to create product:', error.response?.data || error.message);
            alert('Failed to create product');
        } finally {
            setLoading(false);
        }
    };

    const toastMarkup = toastActive ? (
        <div style={{ position: 'fixed', top: '20px', right: '20px', zIndex: 9999 }}>
            <Toast content="Product added successfully!" onDismiss={toggleToastActive} />
        </div>
    ) : null;

    return (
        <Frame>
            {toastMarkup}
            <Page title="Create Product">
                <Layout>
                    <Layout.Section>
                        <Card sectioned>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                                <TextField
                                    label="Product Title"
                                    value={title}
                                    onChange={setTitle}
                                    autoComplete="off"
                                    placeholder="Enter product name"
                                />
                                
                                <label style={{ fontWeight: 500 }}>Description</label>
                                <div ref={summernoteRef} />

                                {/* Grid for Price and Category */}
                                <Grid>
                                    <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 6, xl: 6 }}>
                                        <TextField
                                            label="Price"
                                            type="number"
                                            value={price}
                                            onChange={setPrice}
                                            placeholder="Enter product price"
                                        />
                                    </Grid.Cell>
                                    <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 6, xl: 6 }}>
                                        <Select
                                            label="Category"
                                            options={categoryOptions}
                                            onChange={setCategory}
                                            value={category}
                                        />
                                    </Grid.Cell>
                                </Grid>

                                <DropZone
                                    accept="image/*"
                                    type="image"
                                    onDrop={([file]) => {
                                        setFileUpload(file);
                                        handleImageUpload(file);
                                    }}
                                    allowMultiple={false}
                                    disabled={loading}
                                >
                                    <DropZone.FileUpload />
                                    {imageUrl && (
                                        <div style={{ marginTop: '10px' }}>
                                            <Thumbnail
                                                source={imageUrl}
                                                alt="Uploaded product image"
                                                size="large"
                                            />
                                        </div>
                                    )}
                                </DropZone>
                                <div style={{ textAlign: 'right' }}>
                                    <Button
                                        primary
                                        onClick={handleSubmit}
                                        loading={loading}
                                        disabled={!title.trim()}
                                    >
                                        Save Product
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </Layout.Section>
                </Layout>
            </Page>
        </Frame>
    );
};

export default ProductForm;
