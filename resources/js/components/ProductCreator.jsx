import {
    Card,
    Frame,
    IndexTable,
    Layout,
    Page,
    Text,
    TextField,
    useIndexResourceState,
    Modal,
    DropZone,
    Thumbnail,
    Spinner,
    SkeletonPage,
    SkeletonBodyText,
    TextContainer,
    SkeletonDisplayText,
} from '@shopify/polaris';
import { useEffect, useState, useCallback } from 'react';
import useAxios from '../hooks/useAxios';
import { useNavigate } from 'react-router-dom';

const ProductCreator = () => {
    const navigate = useNavigate();
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeModal, setActiveModal] = useState(false);
    const [productToDelete, setProductToDelete] = useState(false);
    const { axios } = useAxios();

    const [loadingEdit, setLoadingEdit] = useState(false);
    const [loadingDelete, setLoadingDelete] = useState(false);

    const [file, setFile] = useState(null);

    // Fetch products with search query
    const fetchProducts = useCallback((query = '') => {
        setLoading(true);
        axios.get('/products', { params: { query } })
            .then(response => {
                setProducts(response.data);
                setLoading(false);
            })
            .catch(error => {
                console.error('Failed to fetch products:', error);
                setLoading(false);
            });
    }, [axios]);

    useEffect(() => {
        fetchProducts();
    }, [fetchProducts]);

    const handleSearchChange = useCallback((value) => {
        setSearchQuery(value);
        fetchProducts(value);
    }, [fetchProducts]);

    const resourceName = {
        singular: 'product',
        plural: 'products',
    };

    const {
        selectedResources,
        allResourcesSelected,
        handleSelectionChange
    } = useIndexResourceState(products);

    const handleDelete = async () => {
        setLoadingDelete(true);
        alert(productToDelete);
        try {
            const payload = {
                id: productToDelete,
            };
            await axios.post(`hhhhhhhh-delete`, payload);
            setProducts(prev => prev.filter(p => p.id !== productToDelete));
        } catch (error) {
            console.error('Failed to delete product:', error);
            alert('Failed to delete product');
        } finally {
            setLoadingDelete(false);
            setActiveModal(false);
            setProductToDelete(null);
        }
    };

    const [editModalActive, setEditModalActive] = useState(false);
    const [productToEdit, setProductToEdit] = useState(null);
    const [editTitle, setEditTitle] = useState('');
    const [editImage, setEditImage] = useState('');
    

    const handleEdit = async () => {
        setLoadingEdit(true);

        try {
            const payload = {
                title: editTitle,
                image_id: editImage,
            };
            await axios.put(`/products/update`, payload);
            setProducts(prev =>
                prev.map(p =>
                    p.id === productToEdit.id ? { ...p, title: editTitle, image: { src: editImage } } : p
                )
            );
        } catch (error) {
            console.error('Failed to update product:', error.response?.data || error.message);
            alert('Failed to update product');
        } finally {
            setLoadingEdit(false);
            setEditModalActive(false);
            setProductToEdit(null);
            setEditTitle('');
            setEditImage('');
            setFile(null);
        }
    };

    const handleImageUpload = async (file) => {
        if (!file) {
            alert('Please select an image file.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        setLoadingEdit(true);
        try {
            const response = await axios.post('/hello/upload-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setEditImage(response.data.imageUrl);
        } catch (error) {
            console.error('Failed to upload image:', error.response?.data || error.message);
            alert('Failed to upload image');
        } finally {
            setLoadingEdit(false);
        }
    };

    const handleDropZoneDrop = useCallback((_dropFiles, acceptedFiles) => {
        const uploadedFile = acceptedFiles[0];
        setFile(uploadedFile);
        handleImageUpload(uploadedFile);
    }, []);

    const validImageTypes = ['image/gif', 'image/jpeg', 'image/png'];
    const fileUpload = !file && <DropZone.FileUpload />;
    const uploadedFile = file && validImageTypes.includes(file.type) ? (
        <Thumbnail
            size="large"
            alt={file.name}
            source={URL.createObjectURL(file)}
        />
    ) : null;

    return (
        <Frame>
            <Page title="Product List">
                <Layout>
                    <Layout.Section>
                        <button
                            onClick={() => navigate('/products/create')}
                            style={{
                                background: '#006fbb',
                                color: 'white',
                                padding: '10px 20px',
                                border: 'none',
                                borderRadius: '4px',
                                cursor: 'pointer',
                                marginBottom: '20px',
                            }}
                        >
                            Add New Product
                        </button>
                    </Layout.Section>
                    <Layout.Section>
                        <TextField
                            label="Search"
                            value={searchQuery}
                            onChange={handleSearchChange}
                            placeholder="Search products..."
                            clearButton
                            onClearButtonClick={() => handleSearchChange('')}
                        />
                    </Layout.Section>

                    <Layout.Section>
                        {loading ? (
                            <SkeletonPage primaryAction>
                                <Layout>
                                    <Layout.Section>
                                        <Card sectioned>
                                            <SkeletonBodyText />
                                        </Card>
                                        <Card sectioned>
                                            <TextContainer>
                                                <SkeletonDisplayText size="small" />
                                                <SkeletonBodyText />
                                            </TextContainer>
                                        </Card>
                                    </Layout.Section>
                                </Layout>
                            </SkeletonPage>
                        ) : (
                            <Card>
                                {products.length > 0 ? (
                                    <IndexTable
                                        resourceName={resourceName}
                                        itemCount={products.length}
                                        selectedItemsCount={allResourcesSelected ? 'All' : selectedResources.length}
                                        onSelectionChange={handleSelectionChange}
                                        headings={[
                                            { title: 'Product' },
                                            { title: 'Image' },
                                            { title: 'ID' },
                                            { title: 'Actions' },
                                        ]}
                                    >
                                        {products.map((product, index) => (
                                            <IndexTable.Row
                                                id={product.id.toString()}
                                                key={product.id}
                                                selected={selectedResources.includes(product.id.toString())}
                                                position={index}
                                            >
                                                <IndexTable.Cell>{product.title}</IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    {product.image?.src ? (
                                                        <img
                                                            src={product.image.src}
                                                            alt={product.title}
                                                            style={{ width: '50px', height: '50px', objectFit: 'cover', borderRadius: 4 }}
                                                        />
                                                    ) : (
                                                        <Text as="span" tone="subdued">No Image</Text>
                                                    )}
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>{product.id}</IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setProductToEdit(product);
                                                            setEditTitle(product.title);
                                                            setEditImage(product.image?.src || '');
                                                            setEditModalActive(true);
                                                        }}
                                                        style={{
                                                            background: '#006fbb',
                                                            color: 'white',
                                                            padding: '6px 12px',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: loadingEdit ? 'not-allowed' : 'pointer',
                                                            marginRight: '10px',
                                                        }}
                                                        disabled={loadingEdit}
                                                    >
                                                        {loadingEdit ? (
                                                            <Spinner size="small" color="white" />
                                                        ) : (
                                                            'Edit'
                                                        )}
                                                    </button>

                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setProductToDelete(product.id);
                                                            setActiveModal(true);
                                                        }}
                                                        style={{
                                                            background: '#bf0711',
                                                            color: 'white',
                                                            padding: '6px 12px',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: loadingDelete ? 'not-allowed' : 'pointer',
                                                        }}
                                                        disabled={loadingDelete}
                                                    >
                                                        {loadingDelete ? (
                                                            <Spinner size="small" color="white" />
                                                        ) : (
                                                            'Delete'
                                                        )}
                                                    </button>
                                                </IndexTable.Cell>
                                            </IndexTable.Row>
                                        ))}
                                    </IndexTable>
                                ) : (
                                    <Text as="p" alignment="center">
                                        No products found.
                                    </Text>
                                )}
                            </Card>
                        )}
                    </Layout.Section>
                </Layout>

                {/* Delete Modal */}
                <Modal
                    open={activeModal}
                    onClose={() => {
                        setActiveModal(false);
                        setProductToDelete(null);
                    }}
                    title="Are you sure you want to delete this product?"
                    primaryAction={{
                        content: loadingDelete ? 'Deleting...' : 'Delete',
                        onAction: handleDelete,
                        destructive: true,
                        loading: loadingDelete,
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => {
                                setActiveModal(false);
                                setProductToDelete(null);
                            },
                        },
                    ]}
                />

                {/* Edit Modal */}
                <Modal
                    open={editModalActive}
                    onClose={() => {
                        setEditModalActive(false);
                        setProductToEdit(null);
                        setFile(null);
                    }}
                    title="Edit Product"
                    primaryAction={{
                        content: loadingEdit ? 'Saving...' : 'Save',
                        onAction: handleEdit,
                        loading: loadingEdit,
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => {
                                setEditModalActive(false);
                                setProductToEdit(null);
                                setFile(null);
                            },
                        },
                    ]}
                >
                    <Modal.Section>
                        <TextField
                            label="Title"
                            value={editTitle}
                            onChange={setEditTitle}
                            autoComplete="off"
                        />
                        <br />
                        <DropZone
                            allowMultiple={false}
                            accept="image/*"
                            type="image"
                            onDrop={handleDropZoneDrop}
                        >
                            {uploadedFile}
                            {fileUpload}
                        </DropZone>
                        {editImage && (
                            <img
                                src={editImage}
                                alt="Uploaded"
                                style={{ marginTop: 10, width: 100, height: 100, objectFit: 'cover' }}
                            />
                        )}
                    </Modal.Section>
                </Modal>
            </Page>
        </Frame>
    );
};

export default ProductCreator;